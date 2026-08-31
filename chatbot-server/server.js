import dotenv from 'dotenv';
dotenv.config();

import express from 'express';
import cors from 'cors';
import OpenAI from 'openai';

const app = express();
app.use(cors());
app.use(express.json());

const deepseek = new OpenAI({
    baseURL: 'https://openrouter.ai/api/v1',
    apiKey: process.env.OPENROUTER_API_KEY,
    defaultHeaders: {
        'HTTP-Referer': 'http://localhost:3000',
        'X-Title': 'CobraCart E-Commerce Assistant',
    }
});

const FALLBACK_MODELS = [
    'openrouter/free',
    'deepseek/deepseek-chat:free',
    'google/gemini-2.0-flash-exp:free'
];

const STANDARD_REFUSAL = "I can only assist with CobraCart FAQs (how to order, returns/refunds, data handling), product recommendations, and order status inquiries. How may I help you today?";

// Pre-filter non-e-commerce inputs (Math, Coding, Simple Off-Topic)
function isIrrelevantQuery(text) {
    const query = text.trim().toLowerCase();

    // 1. Math expressions or simple equations (e.g., 1+1, 5*10, 100/2, "what is 2 + 2")
    const mathPattern = /^(\d+[\s\+\-\*\/\^%\=]+\d+|\b(what is|calculate|compute|solve)\b.*?\d+)/i;
    if (mathPattern.test(query)) return true;

    // 2. Direct simple math questions like "1+1", "2+2=", "10 - 3"
    if (/^\d+\s*[\+\-\*\/]\s*\d+/.test(query)) return true;

    // 3. Coding/programming requests
    if (/\b(write code|python|javascript|function|html|css|sql|script)\b/i.test(query)) return true;

    // 4. Common trivia / general chit-chat queries
    if (/^(who is|what is the capital|tell me a story|write a poem|sing|meaning of life)/i.test(query)) return true;

    return false;
}

async function generateContentWithFallback(message, systemInstruction) {
    let lastError = null;

    for (const modelName of FALLBACK_MODELS) {
        try {
            const completion = await deepseek.chat.completions.create({
                model: modelName,
                temperature: 0.0, // Absolute minimum randomness to follow negative rules
                messages: [
                    { role: 'system', content: systemInstruction },
                    { role: 'user', content: message }
                ],
            });

            const text = completion.choices[0]?.message?.content;
            
            if (text) return text;
        } catch (error) {
            console.warn(`Model [${modelName}] failed/rate-limited: ${error.message}. Trying next model...`);
            lastError = error;
        }
    }

    throw lastError;
}

app.post('/api/chat', async (req, res) => {
    try {
        const { message } = req.body;

        if (!message) {
            return res.status(400).json({ error: 'Message is required.' });
        }

        // STEP 1: Code-level check to instantly block basic math, trivia, or code queries
        if (isIrrelevantQuery(message)) {
            return res.json({ response: STANDARD_REFUSAL });
        }

        // STEP 2: Strict LLM System Prompt
        const systemInstruction = `CRITICAL ASSISTANT BOUNDARY:
You are strictly an e-commerce assistant for CobraCart. You DO NOT answer math questions (such as 1+1, calculations, or arithmetic), trivia, programming queries, general knowledge, or general conversations.

PERMITTED TOPICS ONLY:
1. STORE FAQS: Ordering process, return & refund policies, privacy/data handling, payment methods, and shipping.
2. PRODUCT RECOMMENDATIONS: Finding CobraCart products based on price limits, budget, categories, or store catalog.
3. ORDER STATUS: Order tracking, delivery status, and order progress.

REFUSAL INSTRUCTIONS:
If the query is math (e.g., 1+1, 2+2, math problems), general knowledge, coding, or unrelated to CobraCart e-commerce, output EXACTLY this response and NOTHING ELSE:
"${STANDARD_REFUSAL}"

FORMATTING:
- Standard plain text only. No Markdown formatting (no asterisks, bolding, or headers).
- Use numbers (1., 2.) or dashes (-) for lists.
- No formal signatures or placeholders.`;

        const responseText = await generateContentWithFallback(message, systemInstruction);

        return res.json({ response: responseText });

    } catch (error) {
        console.error('All models rate-limited or failed:', error);

        return res.status(200).json({ 
            response: "Good day! Our automated system is currently experiencing high inquiry volume. Kindly resend your message in a few moments." 
        });
    }
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`Server listening on port ${PORT}`);
});