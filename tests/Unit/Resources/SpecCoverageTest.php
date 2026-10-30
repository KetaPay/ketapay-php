<?php

declare(strict_types=1);

namespace KetaPay\Tests\Unit\Resources;

use KetaPay\KetaPay;
use KetaPay\Tests\Support\MockTransport;
use KetaPay\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Yaml\Yaml;

/**
 * Every operation in the spec must have an SDK method that sends exactly the method and path the spec
 * defines. Adding an endpoint to the spec (and syncing it here) fails this test until the SDK covers it,
 * which is how every KetaPay SDK stays in step with the API. See SDK_STANDARDS.md in the spec folder.
 */
final class SpecCoverageTest extends TestCase
{
    private const IDS = ['id' => 'x1', 'milestone_id' => 'm1'];

    /** @return array<string, \Closure(KetaPay): mixed> operationId => the SDK call that implements it */
    private static function calls(): array
    {
        $t = ['customerToken' => 't'];
        $id = self::IDS['id'];
        $m = self::IDS['milestone_id'];

        return [
            // Checkout sessions
            'createCheckoutSession' => fn(KetaPay $k) => $k->checkoutSessions->create([
                'amount' => 1,
                'title' => 't',
                'description' => 'twenty characters long',
                'buyer' => ['email' => 'a@b.co'],
                'seller' => ['phone' => '+2348012345678'],
            ]),
            'listCheckoutSessions' => fn(KetaPay $k) => $k->checkoutSessions->list(),
            'getCheckoutSession' => fn(KetaPay $k) => $k->checkoutSessions->retrieve($id),
            'cancelCheckoutSession' => fn(KetaPay $k) => $k->checkoutSessions->cancel($id),
            'createCheckoutSessionBankTransfer' => fn(KetaPay $k) => $k->checkoutSessions->bankTransfer($id, $t),
            // Customer sessions
            'createCustomerSession' => fn(KetaPay $k) => $k->customerSessions->create(['email' => 'a@b.co']),
            'verifyCustomerSession' => fn(KetaPay $k) => $k->customerSessions->verify($id, '123456'),
            // Escrows and milestones
            'listEscrows' => fn(KetaPay $k) => $k->escrows->list(),
            'getEscrow' => fn(KetaPay $k) => $k->escrows->retrieve($id),
            'flagEscrow' => fn(KetaPay $k) => $k->escrows->flag($id, ['reason' => 'banned_seller']),
            'deliverMilestone' => fn(KetaPay $k) => $k->escrows->milestones->deliver($id, $m, [], $t),
            'releaseMilestone' => fn(KetaPay $k) => $k->escrows->milestones->release($id, $m, ['release_code' => '123456']),
            'getMilestoneReleaseCode' => fn(KetaPay $k) => $k->escrows->milestones->releaseCode($id, $m, $t),
            // Disputes and chargebacks
            'openDispute' => fn(KetaPay $k) => $k->disputes->create($id, ['reason' => 'damaged', 'description' => 'Arrived cracked'], $t),
            'listEscrowDisputes' => fn(KetaPay $k) => $k->disputes->listForEscrow($id),
            'getDispute' => fn(KetaPay $k) => $k->disputes->retrieve($id),
            'concedeDispute' => fn(KetaPay $k) => $k->disputes->concede($id, $t),
            'withdrawDispute' => fn(KetaPay $k) => $k->disputes->withdraw($id, $t),
            'submitDisputeEvidence' => fn(KetaPay $k) => $k->disputes->submitEvidence($id, ['statement' => 'Photos attached here'], $t),
            'listDisputeEvidence' => fn(KetaPay $k) => $k->disputes->listEvidence($id, $t),
            'listEscrowChargebacks' => fn(KetaPay $k) => $k->chargebacks->listForEscrow($id),
            'getChargeback' => fn(KetaPay $k) => $k->chargebacks->retrieve($id),
            'submitChargebackEvidence' => fn(KetaPay $k) => $k->chargebacks->submitEvidence($id, ['statement' => 'Courier receipt'], $t),
            // Files
            'uploadFile' => fn(KetaPay $k) => $k->files->create(['file' => "\x01", 'filename' => 'a.pdf', 'purpose' => 'agreement']),
            // Payouts
            'listBanks' => fn(KetaPay $k) => $k->banks->list(),
            'createPayoutAccount' => fn(KetaPay $k) => $k->payoutAccounts->create(['bank_code' => '057', 'account_number' => '0123456789'], $t),
            'confirmPayoutAccount' => fn(KetaPay $k) => $k->payoutAccounts->confirm($id, '123456', $t),
            'listPayoutAccounts' => fn(KetaPay $k) => $k->payoutAccounts->list($t),
            'deletePayoutAccount' => fn(KetaPay $k) => $k->payoutAccounts->del($id, $t),
            // Webhook endpoints and events
            'createWebhookEndpoint' => fn(KetaPay $k) => $k->webhookEndpoints->create(['url' => 'https://example.com/hooks', 'enabled_events' => ['escrow.funded']]),
            'listWebhookEndpoints' => fn(KetaPay $k) => $k->webhookEndpoints->list(),
            'getWebhookEndpoint' => fn(KetaPay $k) => $k->webhookEndpoints->retrieve($id),
            'updateWebhookEndpoint' => fn(KetaPay $k) => $k->webhookEndpoints->update($id, ['status' => 'enabled']),
            'deleteWebhookEndpoint' => fn(KetaPay $k) => $k->webhookEndpoints->del($id),
            'testWebhookEndpoint' => fn(KetaPay $k) => $k->webhookEndpoints->test($id, 'escrow.funded'),
            'rotateWebhookSecret' => fn(KetaPay $k) => $k->webhookEndpoints->rotateSecret($id),
            'listEvents' => fn(KetaPay $k) => $k->events->list(),
            'getEvent' => fn(KetaPay $k) => $k->events->retrieve($id),
            // Sandbox
            'testPayCheckoutSession' => fn(KetaPay $k) => $k->testHelpers->checkoutSessions->pay($id),
            'testSimulateEscrow' => fn(KetaPay $k) => $k->testHelpers->escrows->simulate($id, ['action' => 'deliver']),
        ];
    }

    /** @return array<string, array{method: string, path: string}> operationId => method and path, read from the spec snapshot */
    private static function spec(): array
    {
        $spec = Yaml::parseFile(__DIR__ . '/../../../spec/v1.yaml');
        $ops = [];
        $paths = is_array($spec) && is_array($spec['paths'] ?? null) ? $spec['paths'] : [];
        foreach ($paths as $path => $methods) {
            foreach (is_array($methods) ? $methods : [] as $method => $op) {
                if (is_array($op) && is_string($op['operationId'] ?? null)) {
                    $ops[$op['operationId']] = ['method' => strtoupper((string) $method), 'path' => (string) $path];
                }
            }
        }

        return $ops;
    }

    #[TestDox('reads the operations from the spec')]
    public function testReadsSpec(): void
    {
        self::assertGreaterThan(30, count(self::spec()));
    }

    #[TestDox('[SPEC-001] has an SDK method for every operation in the spec')]
    public function testNoMissing(): void
    {
        $missing = array_keys(array_diff_key(self::spec(), self::calls()));
        self::assertSame([], $missing, 'Add these to the SDK (and to this table): ' . implode(', ', $missing));
    }

    #[TestDox('[SPEC-001] has no SDK method for an operation the spec no longer has')]
    public function testNoStale(): void
    {
        $stale = array_keys(array_diff_key(self::calls(), self::spec()));
        self::assertSame([], $stale, 'Not in the spec anymore: ' . implode(', ', $stale));
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function operations(): iterable
    {
        foreach (array_intersect_key(self::spec(), self::calls()) as $op => $def) {
            yield $op => [$op, $def['method'], $def['path']];
        }
    }

    #[DataProvider('operations')]
    #[TestDox('[SPEC-002] $operation sends the spec method and path')]
    public function testMethodAndPath(string $operation, string $method, string $path): void
    {
        $t = new MockTransport(['body' => ['object' => 'list', 'data' => [], 'has_more' => false, 'next_cursor' => null]]);
        self::calls()[$operation](self::client($t));
        $expected = (string) preg_replace_callback('/\{(\w+)\}/', static fn(array $m): string => self::IDS[$m[1]] ?? $m[1], $path);
        self::assertSame($method, $t->last()->method);
        self::assertSame("/v1$expected", parse_url($t->last()->url, PHP_URL_PATH));
    }
}
