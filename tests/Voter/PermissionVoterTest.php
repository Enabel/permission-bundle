<?php

/*
 * This file is part of the Enabel Permission package.
 * Copyright (c) Enabel <https://enabel.be/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Enabel\Permission\Tests\Voter;

use Enabel\Permission\Tests\DependencyInjection\PermissionDiscoveryPassTest;
use Enabel\Permission\Tests\Fixtures\Article;
use Enabel\Permission\Tests\Fixtures\Permissions\Admin\UserPermissions;
use Enabel\Permission\Tests\Fixtures\Permissions\NewsPermissions;
use Enabel\Permission\Tests\Fixtures\Relation\CreatorRelation;
use Enabel\Permission\Tests\Fixtures\Rule\NotYourselfRule;
use Enabel\Permission\Voter\PermissionVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Security\Core\User\InMemoryUser;

class PermissionVoterTest extends TestCase
{
    private const array HIERARCHY = [
        'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN'],
        'ROLE_ADMIN' => ['ROLE_NEWS_EDITOR'],
        'ROLE_NEWS_EDITOR' => ['ROLE_USER'],
        'ROLE_EMPLOYEE' => ['ROLE_USER'],
        'ROLE_EXTERNAL' => ['ROLE_USER'],
    ];

    private function voter(): PermissionVoter
    {
        return new PermissionVoter(
            PermissionDiscoveryPassTest::fixtures(),
            new RoleHierarchy(self::HIERARCHY),
            new ServiceLocator(['creator' => static fn (): CreatorRelation => new CreatorRelation()]),
            [new NotYourselfRule()],
        );
    }

    /**
     * @param list<string> $roles
     */
    private function token(string $identifier, array $roles): TokenInterface
    {
        return new UsernamePasswordToken(new InMemoryUser($identifier, null, $roles), 'main', $roles);
    }

    public function testRoleGrants(): void
    {
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter()->vote($this->token('ed', ['ROLE_NEWS_EDITOR']), null, ['NEWS_VIEW']));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter()->vote($this->token('emp', ['ROLE_EMPLOYEE']), null, ['NEWS_VIEW']));
    }

    public function testHigherRoleInheritsTheGrantThroughTheHierarchy(): void
    {
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter()->vote($this->token('boss', ['ROLE_SUPER_ADMIN']), null, ['NEWS_VIEW']));
    }

    public function testEnumCaseIsAcceptedAsWellAsItsValue(): void
    {
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter()->vote($this->token('ed', ['ROLE_NEWS_EDITOR']), null, [NewsPermissions::view]));
    }

    public function testUnknownAttributeAbstains(): void
    {
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter()->vote($this->token('boss', ['ROLE_SUPER_ADMIN']), null, ['NEWS_DELETE', 'ROLE_ADMIN']));
    }

    /**
     * @return iterable<string, array{list<string>, int}>
     */
    public static function commenters(): iterable
    {
        yield 'an employee' => [['ROLE_EMPLOYEE'], VoterInterface::ACCESS_GRANTED];
        yield 'an external' => [['ROLE_EXTERNAL'], VoterInterface::ACCESS_DENIED];
        yield 'an external employee' => [['ROLE_EMPLOYEE', 'ROLE_EXTERNAL'], VoterInterface::ACCESS_DENIED];
        yield 'an editor, through the second role of the grant' => [['ROLE_NEWS_EDITOR'], VoterInterface::ACCESS_GRANTED];
        yield 'an admin, inheriting the editor role' => [['ROLE_ADMIN'], VoterInterface::ACCESS_GRANTED];
    }

    /**
     * @param list<string> $roles
     */
    #[DataProvider('commenters')]
    public function testExceptRulesTheGrantOut(array $roles, int $expected): void
    {
        $this->assertSame($expected, $this->voter()->vote($this->token('someone', $roles), null, [NewsPermissions::comment]));
    }

    public function testRelationGrantsForItsSubjectOnly(): void
    {
        $token = $this->token('alice', ['ROLE_EMPLOYEE']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter()->vote($token, new Article('alice'), [NewsPermissions::edit]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter()->vote($token, new Article('bob'), [NewsPermissions::edit]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter()->vote($token, null, [NewsPermissions::edit]), 'Without a subject, no relation can hold.');
    }

    public function testRoleStillGrantsWhereTheRelationDoesNot(): void
    {
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter()->vote($this->token('admin', ['ROLE_ADMIN']), new Article('bob'), [NewsPermissions::edit]));
    }

    public function testRuleRestrictsAGrantedPermission(): void
    {
        $token = $this->token('admin', ['ROLE_ADMIN']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter()->vote($token, new InMemoryUser('bob', null), [UserPermissions::impersonate]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter()->vote($token, new InMemoryUser('admin', null), [UserPermissions::impersonate]));
    }

    public function testAnonymousIsDenied(): void
    {
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter()->vote(new NullToken(), new Article('alice'), [NewsPermissions::edit]));
    }

    public function testReasonsExplainTheDecision(): void
    {
        $vote = new Vote();
        $this->voter()->vote($this->token('ext', ['ROLE_EMPLOYEE', 'ROLE_EXTERNAL']), null, [NewsPermissions::comment], $vote);
        $this->assertSame(['A grant excludes ROLE_EXTERNAL.'], $vote->reasons);

        $vote = new Vote();
        $this->voter()->vote($this->token('alice', ['ROLE_USER']), new Article('alice'), [NewsPermissions::edit], $vote);
        $this->assertSame(['Granted by the relation "creator".'], $vote->reasons);

        $vote = new Vote();
        $this->voter()->vote($this->token('admin', ['ROLE_ADMIN']), new InMemoryUser('admin', null), [UserPermissions::impersonate], $vote);
        $this->assertSame(['Granted by the role ROLE_ADMIN.', 'Restricted by '.NotYourselfRule::class.'.'], $vote->reasons);
    }
}
