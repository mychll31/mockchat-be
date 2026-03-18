<?php

namespace App\Services;

class ChatStageDetection
{
    /**
     * Steps: 1=Greeting, 2=Probing, 3=Empathize, 4=Solution, 5=Value, 6=Offer, 7=Confirmation
     *
     * Analyzes the full conversation (agent + customer messages in order) to detect
     * which stage the agent has reached. Stages must be completed sequentially —
     * a stage only counts if the previous stage was already completed.
     */
    public static function fromConversation(array $messageHistory): int
    {
        // Build per-stage agent text: only agent messages UP TO each exchange
        // This ensures we check what the agent said in context, not just keywords
        $agentTexts = [];
        foreach ($messageHistory as $m) {
            if ($m['sender'] === 'agent') {
                $agentTexts[] = strtolower($m['body']);
            }
        }

        if (empty($agentTexts)) {
            return 1;
        }

        // Check stages sequentially — each stage requires the previous to be done
        $stage = 1;

        // Stage 1: Greeting — agent greeted the customer
        $allText = implode(' ', $agentTexts);
        if (self::matchesGreeting($allText)) {
            $stage = 2;
        } else {
            return $stage;
        }

        // Stage 2: Probing — agent asked questions to understand the customer's needs
        // Must be a genuine question, not just using question words casually
        if (self::matchesProbing($agentTexts)) {
            $stage = 3;
        } else {
            return $stage;
        }

        // Stage 3: Empathize — agent acknowledged/empathized with the customer
        if (self::matchesEmpathy($allText)) {
            $stage = 4;
        } else {
            return $stage;
        }

        // Stage 4: Solution — agent presented a product or solution
        if (self::matchesSolution($allText)) {
            $stage = 5;
        } else {
            return $stage;
        }

        // Stage 5: Value — agent magnified the value (benefits, testimonials, urgency)
        if (self::matchesValue($allText)) {
            $stage = 6;
        } else {
            return $stage;
        }

        // Stage 6: Offer — agent made a direct offer or close attempt
        if (self::matchesOffer($allText)) {
            $stage = 7;
        } else {
            return $stage;
        }

        // Stage 7: Confirmation — agent asked for details to confirm the order
        if (self::matchesConfirmation($allText)) {
            return 7; // All done — but don't auto-advance beyond 7
        }

        return $stage;
    }

    /**
     * Legacy method — kept for backward compatibility but delegates to fromConversation
     * when possible. Falls back to sequential keyword check on agent-only text.
     */
    public static function fromAgentMessages(array $agentBodies): int
    {
        $history = array_map(fn ($body) => ['sender' => 'agent', 'body' => $body], $agentBodies);
        return self::fromConversation($history);
    }

    private static function matchesGreeting(string $text): bool
    {
        return (bool) preg_match('/\b(hi|hello|hey|kumusta|kamusta|magandang (araw|umaga|hapon|gabi)|thank you for (messaging|reaching|contacting)|welcome|good (day|morning|afternoon|evening)|salamat sa pag\-?message|musta)\b/i', $text);
    }

    private static function matchesProbing(array $agentTexts): bool
    {
        // Look for agent messages that contain genuine questions (not just question words in passing)
        foreach ($agentTexts as $msg) {
            // Must contain a question mark OR a multi-word question phrase
            $hasQuestion = str_contains($msg, '?');
            $hasQuestionPhrase = (bool) preg_match('/\b(ano.{0,20}(kailangan|problema|nangyari|gusto|hinahanap)|paano.{0,15}(ka|kita|namin)|tell me more|what.{0,15}(need|looking|happen|issue|problem)|how can (i|we) help|may maitutulong|anong (problema|kailangan|nangyari|hinahanap)|paki\-?(kwento|share|explain)|can you (tell|describe|explain))\b/i', $msg);

            if ($hasQuestion && strlen($msg) > 15) {
                return true;
            }
            if ($hasQuestionPhrase) {
                return true;
            }
        }
        return false;
    }

    private static function matchesEmpathy(string $text): bool
    {
        return (bool) preg_match('/\b(naiintindihan (ko|namin|po)|i understand|gets ko|alam (ko|namin) kung gaano|nakakarelate|sorry to hear|pasensya|naku|kawawa naman|i can (imagine|see)|that must be|we understand|naintindihan|nakaka(inis|frustrate) talaga|i (completely )?understand how|sayang naman|oo nga)\b/i', $text);
    }

    private static function matchesSolution(string $text): bool
    {
        return (bool) preg_match('/\b(i\-?recommend|i?rekomenda|ito (po )?ang|here\'?s what (i|we) (can|suggest)|we (can|have)|mayroon (po )?(kaming|tayong)|perfect (po )?para|meron (po )?(kaming|tayong)|ang (product|produkto) namin|ito ang solusyon|this (will|can|should) help|pwede (po )?namin|let me suggest|try (po )?natin|maganda (po )?ang)\b/i', $text);
    }

    private static function matchesValue(string $text): bool
    {
        return (bool) preg_match('/\b(benefit|benepisyo|testimonial|maraming (nag\-?order|bumili|satisfied)|proven|guarantee|garantiya|limited (offer|stock|time)|best.?seller|dami.{0,10}(review|feedback)|quality|top.?rated|popular|madaming nagsabi|subok na|trusted|mataas ang rating|value for money)\b/i', $text);
    }

    private static function matchesOffer(string $text): bool
    {
        return (bool) preg_match('/\b(gusto mo (po )?ba.{0,20}(order|i\-?try|bilhin|avail)|would you like to (order|try|get|buy)|mag\-?order|i\-?(order|try|avail) (mo|na|po)|place.{0,5}order|shall (we|i) (proceed|process)|ready (ka|na) (po )?(mag|ba)|pwede na (po )?natin i\-?proceed|go (ka|na) (po )?ba|kuha ka na|take (mo|na))\b/i', $text);
    }

    private static function matchesConfirmation(string $text): bool
    {
        return (bool) preg_match('/\b(pangalan (mo|nyo|po)|your (full )?name|address (mo|nyo|po)|i\-?send (mo|po).{0,15}(address|name|number)|payment method|mode of payment|bayad|pano (mo|po) gustong mag\-?bayad|delivery|shipping|padala|contact number|number mo|cp number|cellphone|gcash|bank transfer|cod|cash on delivery|paki\-?(send|bigay).{0,15}(address|details|info)|complete (mo|na) (po )?ang (details|info))\b/i', $text);
    }
}
