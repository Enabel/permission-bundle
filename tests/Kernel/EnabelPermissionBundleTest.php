<?php

/*
 * This file is part of the Enabel Permission package.
 * Copyright (c) Enabel <https://enabel.be/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Enabel\Permission\Tests\Kernel;

use Enabel\Permission\Matrix\PermissionMatrix;
use Enabel\Permission\Tests\Fixtures\Article;
use Enabel\Permission\Tests\Fixtures\Permissions\Admin\UserPermissions;
use Enabel\Permission\Tests\Fixtures\Permissions\NewsPermissions;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Twig\Environment;

class EnabelPermissionBundleTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * @param list<string> $roles
     */
    private function logIn(string $identifier, array $roles): AuthorizationCheckerInterface
    {
        $container = static::getContainer();

        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = $container->get('security.token_storage');
        $tokenStorage->setToken(new UsernamePasswordToken(new InMemoryUser($identifier, null, $roles), 'main', $roles));

        /** @var AuthorizationCheckerInterface $checker */
        $checker = $container->get('security.authorization_checker');

        return $checker;
    }

    public function testTheAppAnswersPermissionsThroughTheSecurityComponent(): void
    {
        self::bootKernel();
        $checker = $this->logIn('alice', ['ROLE_EMPLOYEE']);

        $this->assertTrue($checker->isGranted(NewsPermissions::comment));
        $this->assertFalse($checker->isGranted(NewsPermissions::view));
        $this->assertTrue($checker->isGranted('NEWS_EDIT', new Article('alice')), 'autoconfigured relation');
        $this->assertFalse($checker->isGranted('NEWS_EDIT', new Article('bob')));
    }

    public function testAutoconfiguredRuleRestricts(): void
    {
        self::bootKernel();
        $checker = $this->logIn('admin', ['ROLE_ADMIN']);

        $this->assertTrue($checker->isGranted(UserPermissions::impersonate, new InMemoryUser('bob', null)));
        $this->assertFalse($checker->isGranted(UserPermissions::impersonate, new InMemoryUser('admin', null)));
    }

    public function testMatrixUsesTheApplicationHierarchy(): void
    {
        self::bootKernel();

        /** @var PermissionMatrix $matrix */
        $matrix = static::getContainer()->get('test.permission_matrix');

        $this->assertSame(['ROLE_ADMIN', 'ROLE_EMPLOYEE', 'ROLE_NEWS_EDITOR', 'ROLE_EXTERNAL', 'ROLE_USER'], $matrix->roles());
    }

    public function testMatrixTemplateRenders(): void
    {
        self::bootKernel();

        /** @var Environment $twig */
        $twig = static::getContainer()->get('twig');
        /** @var PermissionMatrix $matrix */
        $matrix = static::getContainer()->get('test.permission_matrix');

        $html = $twig->render('@EnabelPermission/matrix.html.twig', ['matrix' => $matrix]);

        $this->assertStringContainsString('data-domain="News"', $html);
        $this->assertStringContainsString('<code>NEWS_COMMENT</code>', $html);
        $this->assertMatchesRegularExpression('#data-role="ROLE_EMPLOYEE"><span aria-hidden="true">&\#10003;</span>#', $html);
        $this->assertStringContainsString('Also granted by', $html);
        $this->assertStringContainsString('>creator<', $html);
    }

    public function testDebugCommandListsTheGrants(): void
    {
        $tester = new CommandTester((new Application(self::bootKernel()))->find('debug:permission'));

        $tester->execute(['domain' => 'news']);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('NEWS_COMMENT', $tester->getDisplay());
        $this->assertStringContainsString('ROLE_EMPLOYEE | ROLE_NEWS_EDITOR; except: ROLE_EXTERNAL', $tester->getDisplay());
        $this->assertStringContainsString('or relations: creator', $tester->getDisplay());
        $this->assertStringNotContainsString('USER_IMPERSONATE', $tester->getDisplay());
    }
}
