<?php

declare(strict_types=1);

namespace KetaPay\Tests\Contract;

use KetaPay\KetaPay;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * [SPEC-003] Real SDK calls against a Prism mock built from spec/v1.yaml with request validation on.
 * Any request the spec doesn't allow fails here. Run with `composer test:contract`.
 */
final class ContractTest extends TestCase
{
    private const IDS = [
        'session' => '2c9a7f4e-1b3d-4e6a-8f2c-5d7b9e1a3c60',
        'escrow' => '3b1f6c2e-8d4a-4e7b-9c1d-2a5f8e6b7c90',
        'milestone' => '7c2e9a41-3b5d-4f6e-8a1c-9d0b2e4f6a83',
        'dispute' => '8e2f4a6c-1d3b-4c7e-9a5f-0b2d4f6a8c13',
        'file' => '5d8f2b1a-7c3e-4a9d-b6f0-2e4c8a1b3d57',
        'endpoint' => '1f2e3d4c-5b6a-4789-8a9b-0c1d2e3f4a5b',
    ];
    private const CUSTOMER = ['customerToken' => 'ctk_test_contract'];

    private KetaPay $k;

    protected function setUp(): void
    {
        $url = getenv('KETAPAY_MOCK_URL');
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('No Prism mock: run `composer test:contract`');
        }
        $this->k = new KetaPay(['apiKey' => 'sk_test_contract', 'baseUrl' => $url, 'maxRetries' => 0]);
    }

    #[TestDox('[SPEC-003] creates a checkout session with milestones, images and both contacts')]
    public function testCreatesCheckoutSession(): void
    {
        $session = $this->k->checkoutSessions->create([
            'amount' => 150_000_000,
            'category' => 'services',
            'title' => 'Company website',
            'description' => 'Five-page marketing site with blog, built on Next.js.',
            'images' => [['url' => 'https://cdn.example.com/1.jpg']],
            'attachments' => [['name' => 'Brief.pdf', 'file' => self::IDS['file']]],
            'milestones' => [
                ['title' => 'Design', 'amount' => 40_000_000],
                ['title' => 'Build', 'amount' => 110_000_000],
            ],
            'buyer' => ['email' => 'ada@example.com', 'phone' => '+2348012345678'],
            'seller' => ['email' => 'tunde@example.com'],
            'release' => ['method' => 'buyer_confirms', 'auto_release_hours' => 168],
            'commission' => ['rate_bps' => 1000],
            'external_reference' => 'proj_4471',
            'metadata' => ['order' => '4471'],
        ]);
        self::assertSame('checkout_session', $session->object);
    }

    #[TestDox('[SPEC-003] reads and lists')]
    public function testReadsAndLists(): void
    {
        $k = $this->k;
        self::assertSame('checkout_session', $k->checkoutSessions->retrieve(self::IDS['session'])->object);
        self::assertSame('list', $k->checkoutSessions->list(['status' => 'open', 'limit' => 5])->object);
        self::assertSame('escrow', $k->escrows->retrieve(self::IDS['escrow'])->object);
        self::assertSame('list', $k->escrows->list(['status' => 'active', 'limit' => 10])->object);
        self::assertSame('list', $k->events->list(['type' => 'escrow.funded'])->object);
        self::assertSame('list', $k->banks->list()->object);
        self::assertSame('list', $k->chargebacks->listForEscrow(self::IDS['escrow'])->object);
    }

    #[TestDox('[SPEC-003] runs the customer flow')]
    public function testCustomerFlow(): void
    {
        $k = $this->k;
        self::assertSame('customer_session', $k->customerSessions->create(['email' => 'ada@example.com'])->object);
        self::assertSame('customer_token', $k->customerSessions->verify(self::IDS['session'], '123456')->object);
        $k->checkoutSessions->bankTransfer(self::IDS['session'], self::CUSTOMER);
        $k->escrows->milestones->deliver(self::IDS['escrow'], self::IDS['milestone'], ['note' => 'Designs ready'], self::CUSTOMER);
        $k->escrows->milestones->release(self::IDS['escrow'], self::IDS['milestone'], [], self::CUSTOMER);
        $k->escrows->milestones->release(self::IDS['escrow'], self::IDS['milestone'], ['release_code' => '731045']);
        $k->escrows->milestones->releaseCode(self::IDS['escrow'], self::IDS['milestone'], self::CUSTOMER);
        $k->escrows->cancel(self::IDS['escrow'], ['reason' => 'Buyer changed their mind'], self::CUSTOMER);
        $k->checkoutSessions->cancel(self::IDS['session']);
        self::assertSame('flag', $k->escrows->flag(self::IDS['escrow'], ['reason' => 'banned_seller'])->object);
    }

    #[TestDox('[SPEC-003] runs disputes and evidence, with a file upload')]
    public function testDisputes(): void
    {
        $k = $this->k;
        $k->files->create(['file' => 'img', 'filename' => 'photo.jpg', 'purpose' => 'dispute_evidence', 'contentType' => 'image/jpeg'], self::CUSTOMER);
        $k->disputes->create(self::IDS['escrow'], ['reason' => 'not_as_agreed', 'description' => 'Wrong colours used throughout', 'milestone_id' => self::IDS['milestone']], self::CUSTOMER);
        $k->disputes->submitEvidence(self::IDS['dispute'], ['statement' => 'See the brand guide', 'files' => [self::IDS['file']]], self::CUSTOMER);
        $k->disputes->concede(self::IDS['dispute'], self::CUSTOMER);
        $k->disputes->withdraw(self::IDS['dispute'], self::CUSTOMER);
        $k->disputes->retrieve(self::IDS['dispute']);
        self::assertSame('list', $k->disputes->listForEscrow(self::IDS['escrow'])->object);
        self::assertSame('list', $k->disputes->listEvidence(self::IDS['dispute'], self::CUSTOMER)->object);
        $k->chargebacks->submitEvidence(self::IDS['dispute'], ['statement' => 'Courier receipt attached'], self::CUSTOMER);
    }

    #[TestDox('[SPEC-003] manages payout accounts and webhook endpoints')]
    public function testPayoutsAndWebhooks(): void
    {
        $k = $this->k;
        $k->payoutAccounts->create(['bank_code' => '057', 'account_number' => '0123456789'], self::CUSTOMER);
        $k->payoutAccounts->confirm(self::IDS['endpoint'], '123456', self::CUSTOMER);
        self::assertSame('list', $k->payoutAccounts->list(self::CUSTOMER)->object);
        $k->payoutAccounts->del(self::IDS['endpoint'], self::CUSTOMER);
        $endpoint = $k->webhookEndpoints->create(['url' => 'https://example.com/hooks', 'enabled_events' => ['escrow.funded']]);
        self::assertStringStartsWith('whsec_', (string) $endpoint->secret);
        $k->webhookEndpoints->retrieve(self::IDS['endpoint']);
        $k->webhookEndpoints->update(self::IDS['endpoint'], ['status' => 'enabled']);
        self::assertSame('list', $k->webhookEndpoints->list(['limit' => 5])->object);
        $k->webhookEndpoints->test(self::IDS['endpoint'], 'escrow.funded');
        self::assertSame('webhook_secret', $k->webhookEndpoints->rotateSecret(self::IDS['endpoint'])->object);
        $k->webhookEndpoints->del(self::IDS['endpoint']);
    }

    #[TestDox('[SPEC-003] drives the sandbox')]
    public function testSandbox(): void
    {
        $this->k->testHelpers->checkoutSessions->pay(self::IDS['session'], ['method' => 'card']);
        self::assertSame('escrow', $this->k->testHelpers->escrows->simulate(self::IDS['escrow'], ['action' => 'deliver', 'milestone_id' => self::IDS['milestone']])->object);
    }
}
