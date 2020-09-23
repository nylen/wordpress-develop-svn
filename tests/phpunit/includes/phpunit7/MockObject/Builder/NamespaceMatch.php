<?php
/*
 * This file is part of PHPUnit.
 *
 * This file is modified to replace the Match interface with ParametersMatch,
 * to avoid parse errors due to `match` being a reserved keyword in PHP 8.
 * 
 * When the test suite is updated for compatibility with PHPUnit 9.x,
 * this override can be removed.
 * 
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PHPUnit\Framework\MockObject\Builder;

/**
 * Interface for builders which can register builders with a given identification.
 *
 * This interface relates to Identity.
 */
interface NamespaceMatch
{
    /**
     * Looks up the match builder with identification $id and returns it.
     *
     * @param string $id The identification of the match builder
     *
     * @return Match
     */
    public function lookupId($id);

    /**
     * Registers the match builder $builder with the identification $id. The
     * builder can later be looked up using lookupId() to figure out if it
     * has been invoked.
     *
     * @param string $id      The identification of the match builder
     * @param Match  $builder The builder which is being registered
     */
    public function registerId($id, ParametersMatch $builder);
}
