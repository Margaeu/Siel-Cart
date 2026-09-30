// The only place an OpenRouter call is made. It owns the model fallback chain,
// the per-call timeout, and the one budget that covers a whole HTTP request.

// Free OpenRouter models, tried in order until one answers. OpenRouter retires
// and re-slugs free models without notice: the previous list (gemini-2.0-flash-lite,
// llama-3.3-70b:free, deepseek-r1:free, qwen-2.5-coder:free) all returned 404, so
// every AI-answered question fell through to FRIENDLY_ERROR_MESSAGE. Checked
// against GET https://openrouter.ai/api/v1/models on 2026-09-28. If the chat goes
// down again, re-check that list before suspecting anything else. Plain instruct
// models only: reasoning models can return empty content inside the 6s timeout.
export const FALLBACK_MODELS = [
    'google/gemma-4-31b-it:free',
    'qwen/qwen3.8-27b:free',
    'nvidia/nemotron-3-super-120b-a12b:free',
    'google/gemma-4-26b-a4b-it:free'
];

// One request may spend at most this long in total, across reading the question
// and writing the answer, however many models it has to try. ChatController
// gives the Node service 60 seconds; staying well inside that means a slow
// provider produces our own grounded fallback rather than Laravel's timeout
// error, which the widget can only render as "AI Connection Failed".
export const REQUEST_BUDGET_MS = 20000;

// One model attempt. Short on purpose: with four models in the chain, a longer
// per-call timeout would spend the whole budget waiting on the first dead one.
export const PER_CALL_TIMEOUT_MS = 6000;

// Reading the question is the cheaper half and must leave room to answer it.
export const INTERPRET_TIMEOUT_MS = 5000;

// And it may spend at most this much of the request budget in total, however
// many models it has to try. Without the sub-budget a rate-limited chain --
// four models returning 429 or hanging, which is the normal state of the free
// tier -- spent the whole 20s before the keyword fallback even started, so a
// shopper waited 15 seconds for an answer that needed no model at all.
export const INTERPRET_BUDGET_MS = 8000;

export class Deadline {
    constructor(budgetMs, now = () => Date.now()) {
        this.now = now;
        this.expiresAt = now() + budgetMs;
    }

    remaining() {
        return Math.max(0, this.expiresAt - this.now());
    }

    expired() {
        return this.remaining() <= 0;
    }

    // How long a single call may take: its own cap, or whatever is left of the
    // request budget, whichever is smaller.
    slice(preferredMs) {
        return Math.min(preferredMs, this.remaining());
    }

    // A budget for one stage of the request, which can never outlive the
    // request's own. Interpretation uses it so a rate-limited model chain
    // cannot consume the time the answer still needs.
    child(budgetMs) {
        return new Deadline(Math.min(budgetMs, this.remaining()), this.now);
    }
}

// A completion bounded by `timeoutMs`, with the upstream request actually
// cancelled when that passes. The previous Promise.race left the HTTP request
// running after the race was lost: the socket stayed open, the tokens were
// still billed, and four abandoned calls could pile up behind one question.
async function completeOnce(client, model, messages, timeoutMs, temperature) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    try {
        return await client.chat.completions.create(
            { model, temperature, messages },
            { signal: controller.signal }
        );
    } finally {
        clearTimeout(timer);
    }
}

export function createLlm({ client, models = FALLBACK_MODELS, log = console } = {}) {
    // Tries each model until one returns non-empty text, and gives up early once
    // the request budget is gone rather than starting a call it cannot finish.
    async function complete({ system, user, deadline, perCallMs = PER_CALL_TIMEOUT_MS, temperature = 0.2 }) {
        const messages = [
            { role: 'system', content: system },
            { role: 'user', content: user }
        ];

        let lastError = null;

        for (const model of models) {
            // Under a second left in the REQUEST budget is not enough for a
            // round trip; spending it only delays the deterministic answer we
            // are about to fall back to. The test is on what the request has
            // left, not on `perCallMs`, which a caller may set low on purpose.
            if (deadline.remaining() < 1000) {
                log.warn(`Skipping model [${model}]: ${deadline.remaining()}ms left in the request budget.`);
                break;
            }

            const budget = deadline.slice(perCallMs);

            try {
                const completion = await completeOnce(client, model, messages, budget, temperature);
                let text = completion?.choices?.[0]?.message?.content;

                if (typeof text === 'string') {
                    text = text.replace(/^(user\s*safety:\s*safe|user:safe)\s*/i, '').trim();
                    if (text.length > 0) return text;
                }
                throw new Error(`Model [${model}] returned an empty text payload.`);
            } catch (error) {
                log.warn(`Model [${model}] failed/timed out: ${error.message}. Trying next model...`);
                lastError = error;
            }
        }

        throw lastError || new Error('All fallback models failed.');
    }

    return { complete };
}
