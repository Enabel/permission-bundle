<?php

/*
 * This file is part of the Enabel Permission package.
 * Copyright (c) Enabel <https://enabel.be/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Enabel\Permission\Command;

use Enabel\Permission\PermissionRegistry;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Every permission with its grants, as declared: the list to read in a
 * security review.
 */
#[AsCommand('debug:permission', 'List the permissions and who is granted each of them')]
final readonly class DebugPermissionCommand
{
    public function __construct(
        private PermissionRegistry $registry,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Only list this domain (e.g. News for NewsPermissions)')]
        ?string $domain = null,
    ): int {
        $rows = [];
        foreach ($this->registry->all() as $value => $definition) {
            if (null !== $domain && 0 !== strcasecmp($domain, $definition->domain)) {
                continue;
            }

            $grants = [];
            foreach ($definition->grants as $grant) {
                $parts = array_filter([
                    implode(' | ', $grant->roles),
                    [] === $grant->relations ? '' : 'relations: '.implode(' | ', $grant->relations),
                    [] === $grant->except ? '' : 'except: '.implode(' | ', $grant->except),
                ]);
                $grants[] = implode('; ', $parts);
            }

            $rows[] = [$definition->domain, $value, implode("\nor ", $grants)];
        }

        if ([] === $rows) {
            $io->warning(null === $domain ? 'No permission found.' : \sprintf('No permission in the domain "%s".', $domain));

            return Command::SUCCESS;
        }

        $io->table(['Domain', 'Permission', 'Granted to'], $rows);

        return Command::SUCCESS;
    }
}
