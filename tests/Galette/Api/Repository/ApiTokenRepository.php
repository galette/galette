<?php

/**
 * Copyright © 2003-2026 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Galette\Tests\Api\Repository;

use Galette\Api\Repository\ApiTokenRepository;
use Galette\Tests\GaletteTestCase;

/**
 * Tests for ApiTokenRepository — refresh-token lifecycle
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ApiTokenRepositoryTest extends GaletteTestCase
{
    protected int $seed = 20260401040404;

    private ApiTokenRepository $repository;
    /** @var int[] IDs of members whose tokens must be cleaned up after each test */
    private array $createdMemberIds = [];

    /**
     * Set up test
     */
    public function setUp(): void
    {
        parent::setUp();

        // Skip the entire suite if the api_tokens table is not present
        try {
            $probe = $this->zdb->select('api_tokens');
            $probe->limit(1);
            $this->zdb->execute($probe);
        } catch (\Throwable) {
            $this->markTestSkipped('api_tokens table not present in test database.');
        }

        $this->repository = new ApiTokenRepository($this->zdb);
    }

    /**
     * Tear down test
     */
    public function tearDown(): void
    {
        // createRefreshToken inserts outside the test transaction — clean up manually
        if ($this->createdMemberIds !== []) {
            $delete = $this->zdb->delete('api_tokens');
            $delete->where->in('id_adh', $this->createdMemberIds);
            $this->zdb->execute($delete);
        }
        parent::tearDown();
    }

    /**
     * createRefreshToken() returns true and persists the row
     */
    public function testCreateTokenReturnsTrueAndPersists(): void
    {
        $member = $this->getMemberOne();
        $this->createdMemberIds[] = $member->id;

        $result = $this->repository->createRefreshToken(
            $member->id,
            null,
            'test_token_persist_' . $this->seed,
            ['profile:read'],
            3600
        );

        $this->assertTrue($result);

        // Verify the row is actually in the table
        $select = $this->zdb->select('api_tokens');
        $select->where([
            'id_adh'     => $member->id,
            'token_hash' => hash('sha256', 'test_token_persist_' . $this->seed),
            'is_revoked' => false,
        ]);
        $rows = $this->zdb->execute($select);
        $this->assertSame(1, $rows->count());
    }

    /**
     * verifyAndRotate() returns correct data and revokes the token (rotation)
     */
    public function testVerifyAndRotateReturnsDataThenRevokes(): void
    {
        $member = $this->getMemberOne();
        $this->createdMemberIds[] = $member->id;
        $rawToken = 'test_token_rotate_' . $this->seed;

        $this->repository->createRefreshToken($member->id, null, $rawToken, ['profile:read', 'members:write'], 3600);

        // First call: valid — should return payload
        $payload = $this->repository->verifyAndRotate($rawToken, null);

        $this->assertNotNull($payload);
        $this->assertSame($member->id, $payload['id_adh']);
        $this->assertNull($payload['client_id']);
        $this->assertSame('profile:read members:write', $payload['allowed_scope']);

        // Second call: token has been rotated (revoked) — must return null
        $payload2 = $this->repository->verifyAndRotate($rawToken, null);
        $this->assertNull($payload2);
    }

    /**
     * An expired token (TTL = 0) is rejected immediately
     */
    public function testExpiredTokenReturnsNull(): void
    {
        $member = $this->getMemberOne();
        $this->createdMemberIds[] = $member->id;
        $rawToken = 'test_token_expired_' . $this->seed;

        // TTL = 0 → expires_at is set to now (already past by the time we verify)
        $this->repository->createRefreshToken($member->id, null, $rawToken, ['profile:read'], 0);

        // Give the DB time to register the past timestamp
        sleep(1);

        $result = $this->repository->verifyAndRotate($rawToken, null);
        $this->assertNull($result);
    }

    /**
     * revokeAllForUser() makes all tokens for that user invalid
     */
    public function testRevokeAllForUserInvalidatesAllTokens(): void
    {
        $member = $this->getMemberOne();
        $this->createdMemberIds[] = $member->id;

        $tokenA = 'test_revoke_all_a_' . $this->seed;
        $tokenB = 'test_revoke_all_b_' . $this->seed;

        $this->repository->createRefreshToken($member->id, null, $tokenA, ['profile:read'], 3600);
        $this->repository->createRefreshToken($member->id, null, $tokenB, ['members:read'], 3600);

        $this->repository->revokeAllForUser($member->id);

        $this->assertNull($this->repository->verifyAndRotate($tokenA, null));
        $this->assertNull($this->repository->verifyAndRotate($tokenB, null));
    }

    /**
     * verifyAndRotate() with a client_id scope constraint works correctly
     */
    public function testClientIdConstraintIsRespected(): void
    {
        $member = $this->getMemberOne();
        $this->createdMemberIds[] = $member->id;
        $rawToken = 'test_token_client_' . $this->seed;

        $this->repository->createRefreshToken($member->id, 'galette_app', $rawToken, ['profile:read'], 3600);

        // Wrong client_id → should not match
        $wrongClient = $this->repository->verifyAndRotate($rawToken, 'other_app');
        $this->assertNull($wrongClient);

        // Correct client_id → valid
        $correct = $this->repository->verifyAndRotate($rawToken, 'galette_app');
        $this->assertNotNull($correct);
        $this->assertSame('galette_app', $correct['client_id']);
        $this->assertSame($member->id, $correct['id_adh']);
    }

    /**
     * Verifying a completely unknown token returns null without throwing
     */
    public function testUnknownTokenReturnsNull(): void
    {
        $result = $this->repository->verifyAndRotate('this_token_does_not_exist', null);
        $this->assertNull($result);
    }
}
