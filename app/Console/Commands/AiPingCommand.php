<?php

namespace App\Console\Commands;

use App\Ai\Agents\PingAgent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('ai:ping')]
#[Description('Ping the configured AI provider and print a minimal response')]
class AiPingCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $provider = (string) config('ai.default');

        $this->components->info("Pinging AI provider [{$provider}]...");

        try {
            $response = PingAgent::make()->prompt('Ping');
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line((string) $response);

        return self::SUCCESS;
    }
}
