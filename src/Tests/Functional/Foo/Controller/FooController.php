<?php declare(strict_types = 1);
/*
 * This file is part of the KleijnWeb\RestETagBundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KleijnWeb\RestETagBundle\Tests\Functional\Foo\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author John Kleijn <john@kleijnweb.nl>
 */
class FooController
{
    /**
     * @var bool
     */
    private $invoked = false;

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param Request $request
     *
     * @return array
     */
    public function foobarAction(Request $request)
    {
        if ($this->invoked) {
            throw new \LogicException;
        }
        $this->invoked = true;

        return new Response();
    }
}
