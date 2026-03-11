<?php

namespace App\Services;

class ChatStageDetection
{
    /** Steps: 1=Greeting, 2=Probing, 3=Empathize, 4=Solution, 5=Value, 6=Offer, 7=Confirmation */
    public static function fromAgentMessages(array $agentBodies): int
    {
        $text = strtolower(implode(' ', $agentBodies));
        $completed = 0;

        if (preg_match('/\b(hi|hello|hey|kamusta|magandang araw|thank you for messaging|thanks for reaching|welcome|good day|salamat sa pag\-?message)\b/', $text)) {
            $completed = max($completed, 1);
        }
        if (preg_match('/\b(ano|paano|what|how|when|which|tanong|tell me more|gusto ko lang malaman|could you tell|anong problema|what happened|bakit|why)\b/', $text)) {
            $completed = max($completed, 2);
        }
        if (preg_match('/\b(naiintindihan|understand|gets ko|marami ang|experience|alam ko|i understand|nakakarelate)\b/', $text)) {
            $completed = max($completed, 3);
        }
        if (preg_match('/\b(recommend|rekomenda|product|produkto|solution|solusyon|answer|sagot|makakatulong|we can offer|ito ang|this will help)\b/', $text)) {
            $completed = max($completed, 4);
        }
        if (preg_match('/\b(benefit|benepisyo|testimonial|result|limited|urgency|madaming nagsabi|proven|guarantee|garantiya|promo|discount)\b/', $text)) {
            $completed = max($completed, 5);
        }
        if (preg_match('/\b(order|bili|buy|try|bundle|would you like|gusto mo ba|shall we|place order|how many|ilan|isang bote|one bottle)\b/', $text)) {
            $completed = max($completed, 6);
        }
        if (preg_match('/\b(name|pangalan|address|payment|bayad|shipping|padala|contact number|number mo|delivery|entrega)\b/', $text)) {
            $completed = max($completed, 7);
        }

        return (int) min($completed + 1, 7);
    }
}
