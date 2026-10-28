<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Support;

use KetaPay\KetaPay;
use KetaPay\Models\Escrow;
use KetaPay\Models\Event;
use KetaPay\Models\Milestone;
use KetaPay\Tests\Support\TestCase;

/**
 * Base for E2E tests: the real SDK against the staging API with sandbox keys. Skipped when the settings
 * in Env are missing. Every test builds its own data with fresh people, so tests never share state.
 */
/**
 * @phpstan-import-type CreateCheckoutSessionRequest from \KetaPay\Types\Params
 */
abstract class E2ETestCase extends TestCase
{
    private static int $counter = 0;

    protected function setUp(): void
    {
        if (!Env::configured()) {
            self::markTestSkipped('E2E not configured: set KETAPAY_E2E_BASE_URL and KETAPAY_E2E_SECRET_KEY');
        }
    }

    /** Skips the test until the API serves every feature listed (see Served). */
    protected static function requireServed(string ...$features): void
    {
        if (!Served::all(...$features)) {
            self::markTestSkipped('Not served by the API yet: ' . implode(', ', $features));
        }
    }

    /** A client for the staging API. Retries once on network errors. */
    protected static function ketapay(?string $apiKey = null): KetaPay
    {
        return new KetaPay(['apiKey' => $apiKey ?? (string) Env::key(), 'baseUrl' => (string) Env::baseUrl(), 'maxRetries' => 1]);
    }

    /** @return array{buyer: array{email: string, name: string}, seller: array{email: string, name: string}} */
    protected static function people(): array
    {
        $n = ++self::$counter;
        $run = Env::runId();

        return [
            'buyer' => ['email' => "buyer+$run-$n@e2e.ketapay.test", 'name' => 'E2E Buyer'],
            'seller' => ['email' => "seller+$run-$n@e2e.ketapay.test", 'name' => 'E2E Seller'],
        ];
    }

    /**
     * A valid two-milestone services deal, tagged with the run id for cleanup.
     *
     * @param array{buyer: array{email: string, name: string, phone?: string}, seller: array{email: string, name: string}}|null $who
     * @param array<string, mixed>                                                                                           $overrides
     *
     * @return CreateCheckoutSessionRequest
     */
    protected static function sessionParams(?array $who = null, array $overrides = []): array
    {
        $who ??= self::people();

        /** @var CreateCheckoutSessionRequest $params the overrides are valid fields, or deliberately invalid ones (E2E-022) */
        $params = [
            'amount' => 1_500_000,
            'category' => 'services',
            'title' => 'E2E website build',
            'description' => 'Three-page site, built by the e2e suite to test escrow flows.',
            'milestones' => [
                ['title' => 'Design', 'amount' => 500_000],
                ['title' => 'Build', 'amount' => 1_000_000],
            ],
            'buyer' => $who['buyer'],
            'seller' => $who['seller'],
            'release' => ['method' => 'buyer_confirms'],
            'external_reference' => Env::runId() . '-' . self::$counter,
            'metadata' => ['e2e_run' => Env::runId()],
            ...$overrides,
        ];

        return $params;
    }

    /** Signs a customer in with the sandbox code and returns their customer token. */
    protected static function tokenFor(string $email): string
    {
        $k = self::ketapay();
        $session = $k->customerSessions->create(['email' => $email]);

        return (string) $k->customerSessions->verify($session->id, Env::SANDBOX_CODE)->token;
    }

    /**
     * Creates a checkout session, pays it with the test helper and returns the funded escrow and its parties.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array{escrow: Escrow, session: \KetaPay\Models\CheckoutSession, buyer: string, seller: string}
     */
    protected static function fundedEscrow(array $overrides = []): array
    {
        $who = self::people();
        $k = self::ketapay();
        $session = $k->checkoutSessions->create(self::sessionParams($who, $overrides));
        $paid = $k->testHelpers->checkoutSessions->pay($session->id);

        return ['escrow' => $k->escrows->retrieve((string) $paid->escrow_id), 'session' => $paid, 'buyer' => $who['buyer']['email'], 'seller' => $who['seller']['email']];
    }

    protected static function first(Escrow $escrow): Milestone
    {
        return $escrow->milestones[0];
    }

    /**
     * @param \KetaPay\Enums\EventType::* $type
     *
     * @return list<Event> events of a type whose object is, or belongs to, the escrow
     */
    protected static function eventsFor(Escrow $escrow, string $type): array
    {
        $events = self::ketapay()->events->list(['type' => $type, 'limit' => 100])->data;

        return array_values(array_filter($events, static function (Event $e) use ($escrow): bool {
            $object = $e->data->object;

            return $object->id === $escrow->id || $object['escrow_id'] === $escrow->id;
        }));
    }

    /**
     * Retries an assertion until it passes or the time runs out, for effects that land a moment after the
     * API call returns (events, payouts, refunds), instead of checking once and failing on timing.
     */
    protected static function eventually(callable $check, float $timeoutSeconds = 10, float $intervalSeconds = 0.5): void
    {
        $deadline = microtime(true) + $timeoutSeconds;
        while (true) {
            try {
                $check();

                return;
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                if (microtime(true) >= $deadline) {
                    throw $e;
                }
                usleep((int) ($intervalSeconds * 1_000_000));
            }
        }
    }
}
