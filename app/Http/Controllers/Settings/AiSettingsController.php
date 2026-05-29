<?php

namespace App\Http\Controllers\Settings;

use App\Ai\Agents\PingAgent;
use App\Ai\Agents\ProbeAgent;
use App\Http\Controllers\Controller;
use Generator;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedEvent;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AiSettingsController extends Controller
{
    /**
     * Show the AI settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('config/ai', [
            'provider' => config('ai.default'),
        ]);
    }

    /**
     * Stream AI connectivity test as Server-Sent Events.
     */
    public function ping(Request $request): StreamedResponse
    {
        $provider = (string) config('ai.default');
        $prompt = (string) $request->string('prompt', 'Ping');

        if ($prompt === '') {
            $prompt = 'Ping';
        }

        return response()->eventStream(function () use ($provider, $prompt): Generator {
            yield new StreamedEvent(
                event: 'message',
                data: json_encode(['type' => 'step', 'message' => "プロバイダー [{$provider}] に接続中…"]),
            );

            $startedAt = hrtime(true);

            try {
                yield new StreamedEvent(
                    event: 'message',
                    data: json_encode(['type' => 'step', 'message' => "送信: {$prompt}"]),
                );

                $response = $prompt === 'Ping'
                    ? PingAgent::make()->prompt($prompt)
                    : ProbeAgent::make()->prompt($prompt);
                $elapsed = round((hrtime(true) - $startedAt) / 1e6);

                yield new StreamedEvent(
                    event: 'message',
                    data: json_encode(['type' => 'step', 'message' => "レスポンス: {$response}"]),
                );

                $meta = $response->meta;
                $usage = $response->usage;

                if ($meta->model) {
                    yield new StreamedEvent(
                        event: 'message',
                        data: json_encode(['type' => 'step', 'message' => "モデル: {$meta->model}"]),
                    );
                }

                if ($usage->promptTokens || $usage->completionTokens) {
                    yield new StreamedEvent(
                        event: 'message',
                        data: json_encode([
                            'type' => 'step',
                            'message' => "トークン: 入力 {$usage->promptTokens} / 出力 {$usage->completionTokens}",
                        ]),
                    );
                }

                yield new StreamedEvent(
                    event: 'done',
                    data: json_encode(['ok' => true, 'elapsed_ms' => $elapsed]),
                );
            } catch (Throwable $e) {
                Log::warning('AI ping failed', ['error' => $e->getMessage()]);

                yield new StreamedEvent(
                    event: 'done',
                    data: json_encode(['ok' => false, 'error' => $e->getMessage()]),
                );
            }
        });
    }
}
