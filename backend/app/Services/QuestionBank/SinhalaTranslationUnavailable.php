<?php

namespace App\Services\QuestionBank;

use RuntimeException;

/** Raised when no Sinhala translation can be produced (no API key, quota, network, or unusable output). */
class SinhalaTranslationUnavailable extends RuntimeException
{
}
