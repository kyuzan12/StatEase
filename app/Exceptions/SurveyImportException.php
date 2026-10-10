<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an uploaded CSV or Excel file cannot be read. The message is shown to the user.
 */
class SurveyImportException extends RuntimeException
{
}
