<?php

namespace App\Contracts;

interface ChatServiceInterface
{
    public function getCustomerOpener(string $typeKey, string $customerName): string;

    public function getCustomerReply(string $typeKey, string $customerName, array $messageHistory, string $agentMessage): string;
}
