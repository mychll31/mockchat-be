<?php

namespace Tests\Unit;

use App\Services\ChatStageDetection;
use PHPUnit\Framework\TestCase;

class ChatStageDetectionTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Empty / customer-only inputs
    // -----------------------------------------------------------------------

    public function test_returns_1_for_empty_array(): void
    {
        $result = ChatStageDetection::fromConversation([]);

        $this->assertEquals(1, $result);
    }

    public function test_returns_1_for_customer_only_messages(): void
    {
        $result = ChatStageDetection::fromConversation([
            ['sender' => 'customer', 'body' => 'Kumusta po!'],
            ['sender' => 'customer', 'body' => 'May tanong lang ako.'],
        ]);

        $this->assertEquals(1, $result);
    }

    // -----------------------------------------------------------------------
    // Stage 1: Greeting detection
    // -----------------------------------------------------------------------

    public function test_greeting_detection_english(): void
    {
        $greetings = ['Hello po!', 'Hi po! Good day!', 'Hey, how are you?'];

        foreach ($greetings as $greeting) {
            $result = ChatStageDetection::fromConversation([
                ['sender' => 'customer', 'body' => 'Test'],
                ['sender' => 'agent', 'body' => $greeting],
            ]);

            $this->assertGreaterThanOrEqual(2, $result, "Greeting not detected for: {$greeting}");
        }
    }

    public function test_greeting_detection_tagalog(): void
    {
        $greetings = [
            'Kumusta po!',
            'Magandang araw po!',
            'Magandang umaga po!',
            'Magandang hapon!',
            'Magandang gabi po!',
        ];

        foreach ($greetings as $greeting) {
            $result = ChatStageDetection::fromConversation([
                ['sender' => 'customer', 'body' => 'Test'],
                ['sender' => 'agent', 'body' => $greeting],
            ]);

            $this->assertGreaterThanOrEqual(2, $result, "Tagalog greeting not detected for: {$greeting}");
        }
    }

    public function test_no_greeting_stays_at_1(): void
    {
        $result = ChatStageDetection::fromConversation([
            ['sender' => 'customer', 'body' => 'May issue ako.'],
            ['sender' => 'agent', 'body' => 'I will check your order status now.'],
        ]);

        $this->assertEquals(1, $result);
    }

    // -----------------------------------------------------------------------
    // Stage 2: Probing detection
    // -----------------------------------------------------------------------

    public function test_probing_requires_question_mark_or_phrase(): void
    {
        // Question mark + length > 15
        $result = ChatStageDetection::fromConversation([
            ['sender' => 'customer', 'body' => 'Test'],
            ['sender' => 'agent', 'body' => 'Hello po! Magandang araw!'],
            ['sender' => 'customer', 'body' => 'May issue ako.'],
            ['sender' => 'agent', 'body' => 'Ano po ang nangyari sa order nyo?'],
        ]);

        $this->assertGreaterThanOrEqual(3, $result, 'Probing with question mark was not detected');

        // Question phrase without question mark
        $result2 = ChatStageDetection::fromConversation([
            ['sender' => 'customer', 'body' => 'Test'],
            ['sender' => 'agent', 'body' => 'Hello po! Kumusta!'],
            ['sender' => 'customer', 'body' => 'May issue po.'],
            ['sender' => 'agent', 'body' => 'Tell me more about your concern.'],
        ]);

        $this->assertGreaterThanOrEqual(3, $result2, 'Probing with question phrase was not detected');
    }

    public function test_probing_short_question_not_detected(): void
    {
        // "ok?" is too short (length <= 15) and has no question phrase
        $result = ChatStageDetection::fromConversation([
            ['sender' => 'customer', 'body' => 'Test'],
            ['sender' => 'agent', 'body' => 'Hello po!'],
            ['sender' => 'customer', 'body' => 'Problem ko.'],
            ['sender' => 'agent', 'body' => 'ok?'],
        ]);

        // Should remain at stage 2 (greeting done, probing NOT done)
        $this->assertEquals(2, $result);
    }

    // -----------------------------------------------------------------------
    // Stage 3: Empathy detection
    // -----------------------------------------------------------------------

    public function test_empathy_detection(): void
    {
        $empathyPhrases = [
            'Naiintindihan ko po ang sitwasyon nyo.',
            'I understand your frustration.',
            'Sorry to hear about that problem.',
            'Pasensya na po sa abala.',
        ];

        foreach ($empathyPhrases as $phrase) {
            $result = ChatStageDetection::fromConversation([
                ['sender' => 'customer', 'body' => 'Test'],
                ['sender' => 'agent', 'body' => 'Hello po! Kumusta?'],
                ['sender' => 'customer', 'body' => 'May problem po.'],
                ['sender' => 'agent', 'body' => 'Ano po ang nangyari exactly?'],
                ['sender' => 'customer', 'body' => 'Sira yung binili ko.'],
                ['sender' => 'agent', 'body' => $phrase],
            ]);

            $this->assertGreaterThanOrEqual(4, $result, "Empathy not detected for: {$phrase}");
        }
    }

    // -----------------------------------------------------------------------
    // Stage 4: Solution detection
    // -----------------------------------------------------------------------

    public function test_solution_detection(): void
    {
        $solutionPhrases = [
            'I-recommend ko po na mag-file tayo ng replacement.',
            "Here's what we can do para sa situation nyo.",
            'Mayroon po kaming option para dyan.',
            'Ito ang solusyon namin para sa problema nyo.',
        ];

        foreach ($solutionPhrases as $phrase) {
            $result = ChatStageDetection::fromConversation([
                ['sender' => 'customer', 'body' => 'Test'],
                ['sender' => 'agent', 'body' => 'Hello po! Kumusta?'],
                ['sender' => 'customer', 'body' => 'May problem po.'],
                ['sender' => 'agent', 'body' => 'Ano po ang nangyari exactly?'],
                ['sender' => 'customer', 'body' => 'Sira yung item.'],
                ['sender' => 'agent', 'body' => 'Naiintindihan ko po. Pasensya na.'],
                ['sender' => 'customer', 'body' => 'Ano pwede nyo gawin?'],
                ['sender' => 'agent', 'body' => $phrase],
            ]);

            $this->assertGreaterThanOrEqual(5, $result, "Solution not detected for: {$phrase}");
        }
    }

    // -----------------------------------------------------------------------
    // Stage 5: Value detection
    // -----------------------------------------------------------------------

    public function test_value_detection(): void
    {
        $valuePhrases = [
            'Maraming satisfied customers na ang naka-try nito. Quality guarantee po namin.',
            'Ang benefit nito ay long-lasting at durable.',
            'Ito po ang best seller namin with great testimonials.',
            'Proven na po ito, trusted ng maraming buyers.',
        ];

        foreach ($valuePhrases as $phrase) {
            $result = $this->buildConversationUpToStage(5, $phrase);

            $this->assertGreaterThanOrEqual(6, $result, "Value not detected for: {$phrase}");
        }
    }

    // -----------------------------------------------------------------------
    // Stage 6: Offer detection
    // -----------------------------------------------------------------------

    public function test_offer_detection(): void
    {
        $offerPhrases = [
            'Gusto mo po ba mag-order na?',
            'Would you like to order this product?',
            'Shall we proceed with your purchase?',
            'Ready ka na po ba mag-order?',
        ];

        foreach ($offerPhrases as $phrase) {
            $result = $this->buildConversationUpToStage(6, $phrase);

            $this->assertGreaterThanOrEqual(7, $result, "Offer not detected for: {$phrase}");
        }
    }

    // -----------------------------------------------------------------------
    // Stage 7: Confirmation detection
    // -----------------------------------------------------------------------

    public function test_confirmation_detection(): void
    {
        $confirmationPhrases = [
            'Ano po ang pangalan mo at address mo para sa delivery?',
            'Paki-send po ang payment method nyo.',
            'Ano po ang contact number mo para ma-confirm namin?',
            'Gcash or COD po ang payment?',
        ];

        foreach ($confirmationPhrases as $phrase) {
            $result = $this->buildConversationUpToStage(7, $phrase);

            $this->assertEquals(7, $result, "Confirmation not detected for: {$phrase}");
        }
    }

    // -----------------------------------------------------------------------
    // Sequential requirement
    // -----------------------------------------------------------------------

    public function test_stages_must_be_sequential(): void
    {
        // Agent jumps straight to solution without greeting or probing
        $result = ChatStageDetection::fromConversation([
            ['sender' => 'customer', 'body' => 'May problem ako.'],
            ['sender' => 'agent', 'body' => 'I-recommend ko ang product namin.'],
        ]);

        // Without greeting first, should not advance past stage 1
        $this->assertEquals(1, $result);

        // Agent has greeting but jumps to empathy without probing
        $result2 = ChatStageDetection::fromConversation([
            ['sender' => 'customer', 'body' => 'Test'],
            ['sender' => 'agent', 'body' => 'Hello po! Naiintindihan ko ang frustration nyo.'],
        ]);

        // Greeting detected (stage 2), but probing not done so stuck at 2
        $this->assertEquals(2, $result2);
    }

    // -----------------------------------------------------------------------
    // Full 7-stage conversation
    // -----------------------------------------------------------------------

    public function test_full_7_stage_conversation(): void
    {
        $messages = [
            ['sender' => 'customer', 'body' => 'Hi, may tanong ako.'],
            ['sender' => 'agent', 'body' => 'Hello po! Magandang araw!'],
            ['sender' => 'customer', 'body' => 'May issue ako sa order ko.'],
            ['sender' => 'agent', 'body' => 'Ano po ang nangyari sa order nyo?'],
            ['sender' => 'customer', 'body' => 'Hindi dumating yung package ko.'],
            ['sender' => 'agent', 'body' => 'Naiintindihan ko po ang frustration nyo. Pasensya na po.'],
            ['sender' => 'customer', 'body' => 'Ano pwede nyo gawin?'],
            ['sender' => 'agent', 'body' => 'I-recommend ko po na mag-file tayo ng replacement. Ito po ang solusyon namin.'],
            ['sender' => 'customer', 'body' => 'Sigurado ba yan?'],
            ['sender' => 'agent', 'body' => 'Opo, maraming satisfied customers na ang naka-try nito. Quality guarantee po namin yan.'],
            ['sender' => 'customer', 'body' => 'Sige, gusto ko na.'],
            ['sender' => 'agent', 'body' => 'Gusto mo po ba mag-order na? Shall we proceed?'],
            ['sender' => 'customer', 'body' => 'Oo, go na.'],
            ['sender' => 'agent', 'body' => 'Ano po ang pangalan mo at address mo para sa delivery?'],
        ];

        $result = ChatStageDetection::fromConversation($messages);

        $this->assertEquals(7, $result);
    }

    // -----------------------------------------------------------------------
    // Legacy method
    // -----------------------------------------------------------------------

    public function test_from_agent_messages_delegates_to_from_conversation(): void
    {
        // fromAgentMessages wraps each body as ['sender' => 'agent', 'body' => $body]
        // then delegates to fromConversation
        $agentBodies = [
            'Hello po! Magandang araw!',
            'Ano po ang nangyari sa order nyo?',
        ];

        $result = ChatStageDetection::fromAgentMessages($agentBodies);

        // Greeting detected (stage 2), Probing detected (stage 3)
        $this->assertGreaterThanOrEqual(3, $result);

        // Verify empty array returns 1, same as fromConversation
        $this->assertEquals(1, ChatStageDetection::fromAgentMessages([]));
    }

    // -----------------------------------------------------------------------
    // Helper: build a conversation up to a given stage, with the final phrase
    // -----------------------------------------------------------------------

    private function buildConversationUpToStage(int $targetStage, string $finalPhrase): int
    {
        $messages = [
            ['sender' => 'customer', 'body' => 'Hi, may tanong ako.'],
            ['sender' => 'agent', 'body' => 'Hello po! Magandang araw!'],
        ];

        if ($targetStage >= 3) {
            $messages[] = ['sender' => 'customer', 'body' => 'May issue ako sa order ko.'];
            $messages[] = ['sender' => 'agent', 'body' => 'Ano po ang nangyari sa order nyo?'];
        }

        if ($targetStage >= 4) {
            $messages[] = ['sender' => 'customer', 'body' => 'Hindi dumating yung package ko.'];
            $messages[] = ['sender' => 'agent', 'body' => 'Naiintindihan ko po ang frustration nyo. Pasensya na po.'];
        }

        if ($targetStage >= 5) {
            $messages[] = ['sender' => 'customer', 'body' => 'Ano pwede nyo gawin?'];
            $messages[] = ['sender' => 'agent', 'body' => 'I-recommend ko po na mag-file tayo ng replacement.'];
        }

        if ($targetStage >= 6) {
            $messages[] = ['sender' => 'customer', 'body' => 'Sigurado ba yan?'];
            $messages[] = ['sender' => 'agent', 'body' => 'Maraming satisfied customers na. Quality guarantee po namin.'];
        }

        if ($targetStage >= 7) {
            $messages[] = ['sender' => 'customer', 'body' => 'Sige, gusto ko na.'];
            $messages[] = ['sender' => 'agent', 'body' => 'Gusto mo po ba mag-order na? Shall we proceed?'];
        }

        // Add the final phrase being tested
        $messages[] = ['sender' => 'customer', 'body' => 'Sige.'];
        $messages[] = ['sender' => 'agent', 'body' => $finalPhrase];

        return ChatStageDetection::fromConversation($messages);
    }
}
