<?php

namespace Mpdf\Pdf\Protection;

class UniqidGenerator
{

	public function __construct()
	{
		if (!function_exists('random_int') || !function_exists('random_bytes')) {
			throw new \Mpdf\MpdfException(
				'Unable to set PDF file protection, CSPRNG Functions are not available. '
				. 'Use paragonie/random_compat polyfill or upgrade to PHP 7.'
			);
		}
	}

	/**
	 * @return string 32-character uppercase hex string derived from 16 cryptographically secure random bytes
	 */
	public function generate(): string
	{
		return strtoupper(bin2hex(random_bytes(16)));
	}
}
