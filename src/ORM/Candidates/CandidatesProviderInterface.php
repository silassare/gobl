<?php

/**
 * Copyright (c) Emile Silas Sare.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Gobl\ORM\Candidates;

/**
 * A class that narrows a relation's candidates: the records a row may be linked to through it.
 *
 * Named by the relation (`->candidates(ManagerCandidates::class)`, or `'candidates'` in a schema
 * array), and called as `__invoke($context)` with the context class the ORM generator writes for
 * that relation -- `AccountManagerCandidates` for an account's `manager` -- which types the host,
 * the target's query and the caller's context:
 *
 * ```php
 * final class ManagerCandidates implements CandidatesProviderInterface
 * {
 *     public function __invoke(AccountManagerCandidates $c): void
 *     {
 *         $c->selector()->whereTenantIdIs($c->host()->tenant_id);
 *     }
 * }
 * ```
 *
 * The method is not declared here because its parameter is that generated class: an interface
 * declaring `__invoke(CandidatesContext $c)` could not be implemented with a narrower parameter. It
 * is checked when the relation's table is locked.
 *
 * What it narrows is every candidate: the relation's own filters are not applied beside it, since
 * a relation that needs a provider is one whose filters cannot be applied without the host.
 */
interface CandidatesProviderInterface {}
