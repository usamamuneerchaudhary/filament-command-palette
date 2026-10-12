<?php

namespace Usamamuneerchaudhary\CommandPalette\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class CommandNotAuthorized extends HttpException
{
    public static function make(string $id): self
    {
        return new self(403, "The command [{$id}] is not available to this user.");
    }

    public function __construct(int $statusCode = 403, string $message = '')
    {
        parent::__construct($statusCode, $message);
    }
}
