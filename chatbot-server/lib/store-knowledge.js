// Everything the assistant is allowed to say about how the store works, and the
// fixed answers for the published FAQ. Extracted from server.js unchanged so the
// pipeline and its tests share one copy.

// Said when the question is outside what this assistant covers. It names what the
// assistant is and what it was given to work with, rather than reciting the FAQ
// menu: a shopper who asked about the weather learns why the answer is no, and the
// wording stays true for every off-topic question instead of listing three of them.
export const STANDARD_REFUSAL = "I'm an AI shopping assistant for Siel Cart, the official CLSU store run by the UBAP Office. I'm trained to help with our merchandise, product details, store policies, and shopping support — not general topics outside the store. How may I help you today?";

export const FRIENDLY_ERROR_MESSAGE = "Our assistant is temporarily unavailable. Please browse our catalog on the store page or contact the UBAP Office directly for immediate assistance.";

// Said when MySQL itself is unreachable. Deliberately different from "nothing
// matched" and from "that item is sold out": with no catalogue we do not know
// which of those is true, and answering "we have nothing in stock" would be a
// claim about the shop rather than about our own failure.
export const CATALOG_UNAVAILABLE_MESSAGE = "I can't reach our product catalog right now, so I can't check what's in stock. Please try again shortly or browse the [catalog](/products) directly.";

/**
 * Static store facts context provided to LLM
 */
// Kept in sync with CheckoutPage, Order, CancelOrderModal, and the FAQ_ENTRIES
// below: the model may only describe the store in these terms.
export const STORE_FACTS = `STORE FACTS (Siel Cart - UBAP Office at CLSU; UBAP = University Business Affairs Program):
Siel Cart is pickup-only and cash-only at the UBAP Office. No delivery, no couriers, no cards/GCash/online payments.

ACCOUNTS:
- A free account with a verified email is required to place an order. You must be at least 13 years old to register.
- Forgotten password: use **Forgot password?** on the login page. A logged-in customer changes it under **My Account → Profile → Change Password**.
- **My Account → Profile** lets a customer update their first name, last name and phone number, and change their email (Change Email Address, needs the current password). Date of birth cannot be changed there.
- A customer can delete their own account from **My Account → Profile → Delete Account**. It is permanent and is blocked while any order is Pending, Processing or Ready for Pickup.
- Chatbot conversations are not recorded or stored.

HOW TO ORDER:
1. **Browse** catalog and select item...
2. **Choose** size/variant and add to cart.
3. **Open** cart items.
4. **Proceed** to checkout to confirm.
5. **Receive** an email when the order is ready, with the claim number, then collect and pay in cash at UBAP Office.

PROCESSING & PICKUP:
- Allow at least 2-3 days for an order to be processed. Do not promise an exact ready date.
- The customer is emailed when the order is **Ready for Pickup**; that email has the claim number, pickup date and time.
- Claim Numbers are issued ONLY when status is **Ready for Pickup**. Show it at the UBAP Office.
- A customer may authorize another person to collect the order. That person must give the correct claim number, which UBAP verifies before release. Customers must share the claim number only with their authorized representative; UBAP is not responsible for losses, disputes, or unauthorized claims from the customer's voluntary disclosure of the claim number, provided UBAP followed its verification procedures.
- Orders not claimed within the pickup schedule are NOT cancelled automatically by the system: UBAP staff cancel them and the items return to stock. There is no rescheduled pickup; questions about a cancelled order go to ubap@clsu.edu.ph.
- There is no reschedule feature on the website.

CANCELLATION AND CHANGES:
- Customers cancel from the **My Orders** page ONLY while status is **Pending**. Once **Processing**, it cannot be cancelled online.
- Items and quantities cannot be edited after an order is placed. A Pending order can be cancelled and placed again.
- Nothing is charged online, so a cancellation involves no refund.

REVIEWS:
- Only after an order is **Completed**, from the product page's Reviews tab.

RETURNS & PRIVACY:
- Returns/refunds cannot be requested on website. Contact **UBAP Office** directly (ubap@clsu.edu.ph) for defective items.
- There is no in-app inquiry form; other questions go to ubap@clsu.edu.ph or the UBAP Office.
- The UBAP Office is part of Central Luzon State University, Science City of Muñoz, Nueva Ecija 3119. Its opening hours are not published here: do not state any hours; point to ubap@clsu.edu.ph and the pickup time in the Ready for Pickup email.
- Data privacy concerns go to the CLSU Data Protection Officer, dpo@clsu.edu.ph.
- Privacy Policy: [Privacy Policy](/privacy-policy)
- Terms & Conditions: [Terms & Conditions](/terms-and-conditions)`;

export function isIrrelevantQuery(text) {
    const query = text.trim().toLowerCase();

    // "what is" only counts as maths when a number/bracket follows it directly; the old
    // \b(what is)\b.*?\d+ form also refused "what is available under 300".
    const mathPattern = /^(\d+[\s\+\-\*\/\^%\=]+\d+|what is\s*[\d(]|\b(calculate|compute|solve)\b.*?\d+)/i;
    if (mathPattern.test(query)) return true;
    if (/^\d+\s*[\+\-\*\/]\s*\d+/.test(query)) return true;
    if (/\b(write code|python|javascript|function|html|css|sql|script)\b/i.test(query)) return true;
    if (/^(who is|what is the capital|tell me a story|write a poem|sing|meaning of life)/i.test(query)) return true;

    return false;
}

// --- FAQ ANSWERS ------------------------------------------------------------
// Fixed replies for the store's published FAQ. They are answered here, before
// any model call, so they are instant, identical every time, and still work
// when OpenRouter's free models are rate-limited or gone. Keep the wording in
// step with STORE_FACTS above and with what the store really does (Order,
// CancelOrderModal, CheckoutPage, the Terms & Conditions).
const contactUbap = 'email **ubap@clsu.edu.ph** or visit the UBAP Office';

// A matcher is a regex or a function of the text, so all()/any() can nest.
const runMatcher = (m, t) => (typeof m === 'function' ? m(t) : m.test(t));
const all = (...matchers) => (t) => matchers.every(m => runMatcher(m, t));
const any = (...matchers) => (t) => matchers.some(m => runMatcher(m, t));

// Order matters: the first entry that matches wins, so narrower questions
// (missed pickup, cancel) sit above broader ones (ready notice, payment).
export const FAQ_ENTRIES = [
    {
        // First, because asking what the assistant is has to be answered by the
        // assistant itself and not by the model: "are you human" otherwise matched
        // the contact entry and came back with the UBAP email address. The answer
        // says the same thing STANDARD_REFUSAL does, so a shopper who asks what it
        // knows and one who asks it something off-topic hear one consistent scope.
        id: 'identity',
        match: any(
            // "what are you selling" is a catalogue question, not an identity one.
            /^(who|what) (are|r) (you|u)\b(?!\s*(sell|selling|offer|offering|have|got|stock))/,
            /\b(are|r) (you|u) (a |an )?(bot|robot|ai|human|real|person|machine)\b/,
            /\bhow (are|were|did) (you|u) (get )?(trained|train|made|built|created|programmed)\b/,
            /\b(what|which) (ai|model|llm|chatbot|language model)\b.*\b(are|is|do) (you|u|this)\b/,
            /\b(who|what company) (made|created|built|developed|programmed|trained) (you|u)\b/,
            /\b(chatgpt|gpt-?[0-9]*|openai|gemini|claude)\b/
        ),
        answer: "I'm an AI shopping assistant for **Siel Cart**, the official CLSU merchandise store run by the UBAP Office. I'm trained on our product catalog and store policies, so I can help with merchandise, product details, ordering, pickup and payment — not general topics outside the store."
    },
    {
        // Above everything else: "I forgot my password" used to be read as a
        // missed pickup because of the word "forgot".
        id: 'password',
        match: any(
            /\b(forgot(ten)?|forget|lost|reset|change|recover|new)\b.*\bpassword\b/,
            /\bpassword\b.*\b(forgot(ten)?|reset|change|recover|not working|wrong)\b/,
            /\b(can'?t|cannot|unable to|won'?t let me) (log ?in|sign ?in)\b/
        ),
        answer: 'On the login page, choose **Forgot password?** and enter your account email: we will send you a link to set a new one. If you are already logged in, you can change it under **My Account → Profile → Change Password**.'
    },
    {
        id: 'verify-email',
        match: any(
            /\bverif\w*\b.*\b(email|e-mail|link|account)\b/,
            /\b(email|e-mail|link)\b.*\bverif\w*\b/,
            /\b(didn'?t|did not|haven'?t|have not|not) (receive|get|got)\b.*\b(email|link|code)\b/
        ),
        answer: 'After you register, we email you a **verification link**, and you need to verify before you can check out or use My Account. Check your **spam/junk** folder first. If it is not there, log in and use the **resend** option on the verification page (please wait a moment between requests).'
    },
    {
        id: 'refund-cancel',
        match: all(/\b(refund|money back|charged?|reimburse\w*)\b/, /\bcancel/),
        answer: 'Nothing is charged online, because payment is only collected in cash when you pick up your order. So there is nothing to refund when an order is cancelled.'
    },
    {
        id: 'returns',
        match: /\b(returns?|refunds?|exchange[sd]?|defective|damaged|faulty|wrong item)\b/,
        answer: `Returns, refunds, and exchanges can't be requested on the website. For a defective, damaged, or wrong item, ${contactUbap} directly.`
    },
    {
        id: 'delivery',
        match: /\b(deliver\w*|shipping|ship|courier|shipping fee)\b/,
        answer: 'Siel Cart is **pickup-only**: there is no delivery or shipping. Collect your order at the UBAP Office and pay in cash there.'
    },
    {
        id: 'missed-pickup',
        match: any(/\b(miss(ed)?|unclaimed|deadline)\b/, all(/\b(forgot(ten)?|forget)\b/, /\b(pick|claim|collect)/), /\bhold(ing)? (period|my order)\b/, /\bfail(ed)? to (claim|pick)/, /\bnot (picked up|claimed)\b/),
        // The Terms (6.3, 7.3) say an unclaimed order is cancelled by UBAP and its
        // stock restored; they no longer describe a rescheduled pickup.
        answer: `Once your order is **Ready for Pickup**, claim it at the UBAP Office within the pickup date and time given in your email. If it isn't claimed in time, UBAP will cancel the order and the reserved items go back into stock for other customers. There is no reschedule feature on the website; for questions about a cancelled order, ${contactUbap}.`
    },
    {
        id: 'why-cancelled',
        match: any(/\b(why|how come)\b.*\bcancel/, /\b(my order|it) (was|got|has been|is) cancel/),
        answer: `An order is cancelled in one of two ways: **you** cancelled it while it was still Pending, or **UBAP** cancelled it because it wasn't claimed within its pickup schedule. Either way the reserved items go back into stock. Your order page under **My Orders** shows its status. If you don't recognize the cancellation, ${contactUbap}.`
    },
    {
        id: 'cancel',
        match: /\bcancel(l?ed|l?ing|lation)?\b/,
        answer: 'You can cancel only while your order is still **Pending**: open it under **My Orders** and use the cancel option. Once it is marked **Processing**, it can no longer be cancelled online.'
    },
    {
        // Terms 5.6: no editing items or quantities after placing an order.
        id: 'modify-order',
        match: all(/\b(change|edit|modify|update|add|remove|swap|replace)\b/, /\b(my order|the order|order details|size|quantity|item|items|variant)\b/, /\b(after|already|placed|submitted|once|ordered)\b/),
        answer: `Items and quantities can't be edited after an order is placed. If your order is still **Pending**, you can cancel it under **My Orders** and place a new one with the right items (if they are still in stock). Once it is **Processing**, it can't be cancelled online, so ${contactUbap}.`
    },
    {
        id: 'someone-else',
        match: all(/\b(someone|somebody|another person|other person|representative|friend|relative|behalf)\b/, /\b(pick|claim|collect)/),
        answer: "Yes. You may authorize another person to collect your order on your behalf. They must provide the correct **claim number** for the order, which UBAP verifies before releasing it. Share your claim number only with your authorized representative: UBAP is not responsible for losses, disputes, or unauthorized claims that result from you giving out the claim number, as long as UBAP followed its verification procedures."
    },
    {
        id: 'bring',
        match: any(/\bclaim (number|code)\b/, all(/\b(bring|show|present|need|requirements?|required)\b/, /\b(pick|claim|collect)/)),
        answer: 'Yes, show your **claim number** at the UBAP Office. You can find it in your Ready for Pickup email and on your order page under **My Orders**.'
    },
    {
        id: 'how-long',
        match: /\b(how long|how many (days|weeks)|how soon|processing time|turnaround|when will my order (be )?(ready|done|processed))\b/,
        answer: 'Please allow **at least 2–3 days** for your order to be processed. You will get an email once it is ready for pickup, and you can follow its status under **My Orders**.'
    },
    {
        id: 'ready-notice',
        match: all(/\b(how (will|do|can|would) i know|notif\w*|notify|email|alert|inform)\b/, /\b(ready|pick[- ]?up|claim|processing|status)\b/),
        answer: 'You will receive an **email** when your order status changes to **Ready for Pickup**. It includes your claim number and your pickup date and time. You can also check the status any time under **My Orders**.'
    },
    {
        // The Terms (section 18) give the institution's address but not the
        // office's own hours, so hours are deliberately not guessed at.
        id: 'office-location',
        match: any(
            /\b(address|located|location|directions?)\b.*\b(ubap|office)\b/,
            /\b(ubap|office)\b.*\b(address|located|location|directions?)\b/,
            /\bwhere (is|are) (the )?(ubap|office)\b/,
            /\b(physical|actual) (store|shop|office)\b/
        ),
        answer: `The UBAP Office is part of **Central Luzon State University, Science City of Muñoz, Nueva Ecija 3119**. Siel Cart has no separate physical store: you order online and collect your order at the UBAP Office. For directions or the office's opening hours, ${contactUbap}.`
    },
    {
        id: 'office-hours',
        match: any(/\b(office|store|shop|ubap)\b.*\b(hours|open|opens|opening|close|closes|closing|schedule)\b/, /\b(opening|office|business|store) hours\b/, /\bwhat time\b.*\b(open|close)/),
        answer: `I don't have the UBAP Office's opening hours. Your **Ready for Pickup** email gives the exact pickup date and time for your order. For anything else, ${contactUbap}.`
    },
    {
        id: 'where-pickup',
        match: any(/\bwhere\b.*\b(pick|claim|collect)/, /\bpick[- ]?up (location|place|address|area|schedule|time|date|hours)\b/, /\bwhere (is|are) (the )?(ubap|office)\b/, /\bwhen (can|do|should) i (pick|claim|collect)/),
        answer: 'Orders are picked up at the **UBAP Office** (University Business Affairs Program). Once your order is **Ready for Pickup**, you will get an email with your claim number, pickup date, and pickup time. The same details appear under **My Orders**.'
    },
    {
        id: 'same-last-item',
        match: /\b(same time|last (item|one|piece|stock)|two people|both order|simultaneous(ly)?)\b/,
        answer: 'The first order to complete checkout gets the item. If someone else takes the last one first, you will see a message that only a limited quantity is left (or that it is unavailable), and you can adjust your cart. Nothing is charged online, since payment is only collected in cash at pickup, so no refund is needed.'
    },
    {
        id: 'stock',
        match: /\b(in stock|out of stock|sold out|availability|stock status)\b/,
        answer: "Availability is shown on each product's page. Items that are out of stock are clearly marked and can't be checked out, and your cart flags any item that is no longer available."
    },
    {
        id: 'age',
        match: /\b(age (requirement|limit|restriction)|minimum age|how old|old enough|13 years)\b/,
        answer: 'Yes. You must be at least **13 years old** to create an account, in line with our [Privacy Policy](/privacy-policy).'
    },
    {
        // Terms 15: a customer can delete their own account unless an order is
        // still active (Customer::deleteAccount() enforces the same rule).
        id: 'delete-account',
        match: any(/\b(delete|remove|close|deactivate|cancel)\b.*\b(my )?account\b/, /\baccount\b.*\b(delet\w*|remov\w*|clos\w*|deactivat\w*)\b/),
        answer: 'You can delete your account yourself under **My Account → Profile → Delete Account**. It is permanent, and it is not allowed while you have an order that is **Pending**, **Processing**, or **Ready for Pickup**: wait until those orders are completed or cancelled first.'
    },
    {
        // Terms 3: name, email and phone can be updated; date of birth cannot.
        id: 'update-profile',
        match: all(/\b(change|update|edit|modify|correct|fix)\b/, /\b(email|e-mail|phone|contact number|mobile|first name|last name|my name|profile|birthday|birthdate|date of birth)\b/),
        answer: 'Open **My Account → Profile**. You can update your **name** and **phone number** there, and change your **email** with the Change Email Address option (it asks for your current password). Your **date of birth** cannot be changed there.'
    },
    {
        id: 'account',
        match: any(/\b(need|require[sd]?|must|have to)\b.*\b(account|log ?in|sign ?up|register|registration)\b/, /\b(create|make|open)\b.*\baccount\b/),
        answer: 'Yes. You need a free Siel Cart account (with a verified email address) to place an order. An account lets you track your order status, view your order history, and leave reviews after pickup.'
    },
    {
        id: 'review',
        match: all(/\b(leave|write|post|give|add|submit|can i|how (do|can) i)\b/, /\b(review|reviews|rating|rate|feedback)\b/),
        answer: "Yes. Once your order is **Completed**, open the product's page and use its **Reviews** tab to leave a rating and review. You can also reach it from your order under **My Orders**."
    },
    {
        // Terms 10.4.
        id: 'chat-privacy',
        match: any(
            /\b(chat|chats|chatbot|conversations?)\b.*\b(saved?|stored?|recorded?|kept|logged|private)\b/,
            /\b(saved?|stored?|recorded?|kept|logged)\b.*\b(chat|chats|chatbot|conversations?)\b/
        ),
        answer: 'Chatbot conversations are **not recorded or stored** by Siel Cart.'
    },
    {
        id: 'privacy',
        match: /\b(privacy|personal (information|data|info)|data (privacy|protection|handling)|data protection officer|dpo|is my data|my data|my information)\b/,
        answer: 'We only collect the information needed to process your orders and manage your account. For full details, see our [Privacy Policy](/privacy-policy). For data privacy concerns, email the CLSU Data Protection Officer at **dpo@clsu.edu.ph**.'
    },
    {
        id: 'contact',
        match: /\b(contact|inquiry|inquire|enquir\w*|customer (service|support)|support|human|hotline|phone number|email address|reach (you|ubap|someone)|not answered|other question)\b/,
        answer: `If your question isn't covered here, ${contactUbap}. You can also keep asking me about ordering, pickup, payment, and products.`
    },
    {
        id: 'payment',
        match: /\b(payment|paying|pay|gcash|cash|(credit|debit) cards?)\b/,
        answer: 'Payment at Siel Cart is **Cash on Pickup only**, paid in person at the UBAP Office when collecting your items. We do not accept online payments or credit/debit cards.'
    },
    {
        id: 'order-status',
        match: /\b(order status|check my order|track(ing)? (my )?(order|status)|track status|where is my order)\b/,
        answer: `To check your order status:

1. Log in to your **Siel Cart** account.
2. Go to **My Orders** and select your order.
3. Statuses shown are: **Pending**, **Processing**, **Ready for Pickup**, or **Completed** (or **Cancelled**).`
    },
    {
        id: 'how-to-order',
        match: /\b(how (to|do i|can i|would i) (place |make )?(an? )?order|place an order|ordering process|how does ordering work|how (to|do i) buy)\b/,
        answer: `To place an order:

1. **Browse** our catalog and select an item.
2. **Choose** your preferred size or variant, then add it to your cart.
3. **Open** your cart and review your items.
4. **Proceed** to checkout (you'll need to log in) and check your order details.
5. **Submit** your order, then wait for the email that says it is ready.
6. **Collect** it and pay in cash at the UBAP Office, showing your claim number.`
    },
    {
        id: 'about',
        match: /\b(what is siel ?cart|about siel ?cart|what is this (store|shop|website|site)|who (runs|owns|operates|manages|is behind|sells)|is (this|it|siel ?cart)( (store|shop|site|website))? (legit|official|real|safe|trusted|legitimate)|official (store|shop))\b/,
        answer: '**Siel Cart** is the official online store for CLSU merchandise, run by the UBAP Office (University Business Affairs Program) of Central Luzon State University. It is pickup-only and cash-only: order online, then collect and pay in cash at the UBAP Office.'
    },
    {
        // Anchored so it only catches a message that is nothing but a courtesy;
        // "thanks, and how do I cancel?" still reaches the entry for cancelling.
        id: 'thanks',
        match: /^(thanks?( you)?( (so|very) much)?|thank you|salamat( po)?)[!. ]*$/,
        answer: "You're welcome! Ask me anything else about ordering, pickup, payment, or our products."
    },
    {
        id: 'acknowledged',
        match: /^(ok(ay)?( po)?|got it|noted|great|cool|sige|alright)[!. ]*$/,
        answer: 'Alright! Let me know if you need anything else about ordering, pickup, payment, or our products.'
    },
    {
        id: 'goodbye',
        match: /^(bye|goodbye|good bye|see you|see ya|that'?s all|that is all|no,? thanks?)[!. ]*$/,
        answer: 'Goodbye! Thanks for shopping at Siel Cart.'
    }
];

const GREETINGS = ['hi', 'hello', 'hey', 'good morning', 'good afternoon', 'good evening', 'kumusta', 'yo', 'halu'];

export const GREETING_ANSWER = "Hello! Welcome to **Siel Cart**. How can I assist you with your shopping today?";

export const normalizeForMatching = (message) =>
    message.toLowerCase().replace(/[’‘]/g, "'").replace(/\s+/g, ' ').trim();

// The matched entry rather than just its text, so a caller can tell a courtesy
// ("thanks") from a policy answer ("payment") -- a question that asks about a
// policy AND about products has to keep both halves, and only a policy entry is
// worth pairing with a recommendation.
export function findFaqEntry(message) {
    const text = normalizeForMatching(message);

    if (GREETINGS.some(g => text === g || text === g + '!' || text === g + '.')) {
        return { id: 'greeting', answer: GREETING_ANSWER };
    }

    for (const entry of FAQ_ENTRIES) {
        if (runMatcher(entry.match, text)) return entry;
    }
    return null;
}

export function findFaqAnswer(message) {
    return findFaqEntry(message)?.answer ?? null;
}

// A courtesy or greeting is the whole message by construction (those entries are
// anchored), so it is never half of a mixed question.
export const CONVERSATIONAL_FAQ_IDS = new Set(['greeting', 'thanks', 'acknowledged', 'goodbye']);
