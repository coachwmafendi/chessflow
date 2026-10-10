<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

class SeoController
{
    public function robots(): Response
    {
        $body = implode("\n", [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /pratonton',
            'Disallow: /dev',
            '',
            'Sitemap: '.route('sitemap'),
            '',
        ]);

        return response($body)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /** Public pages only; everything behind login stays out of search results. */
    public function sitemap(): Response
    {
        $urls = collect(['home', 'tentang', 'istilah', 'privasi', 'terma', 'murid.login', 'register'])
            ->filter(fn (string $name) => Route::has($name))
            ->map(fn (string $name) => route($name));

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".view('sitemap', ['urls' => $urls])->render();

        return response($xml)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
