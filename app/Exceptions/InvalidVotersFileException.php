<?php

namespace App\Exceptions;

use Exception;

// Thrown by voter-allowlist import classes when the uploaded file is
// missing a required column — caught by the admin controller and shown
// as a normal validation error instead of a 500 page.
class InvalidVotersFileException extends Exception
{
}
