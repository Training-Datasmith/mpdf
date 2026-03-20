<?php

declare (strict_types=1);
namespace Mpdf;

interface Asset_Fetcher_Interface
{
    /**
     * Fetch data from a given path, either local or remote.
     *
     * @param string $path The path to fetch data from.
     * @param string|null $originalSrc The original source path, if applicable.
     * @return string The fetched data.
     * @throws \Mpdf\Exception\AssetFetchingException If fetching fails.
     */
    public function fetch_data_from_path($path, $original_src = null);
}