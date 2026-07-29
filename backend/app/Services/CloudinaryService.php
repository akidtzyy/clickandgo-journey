<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    private string $cloudName;
    private string $apiKey;
    private string $apiSecret;

    public function __construct()
    {
        $this->cloudName = config('services.cloudinary.cloud_name');
        $this->apiKey    = config('services.cloudinary.api_key');
        $this->apiSecret = config('services.cloudinary.api_secret');
    }

    /**
     * Upload a file to Cloudinary and return its secure URL.
     * Returns null on failure.
     *
     * @param  UploadedFile  $file
     * @param  string        $folder  — Cloudinary folder path (e.g. "identities")
     */
    public function upload(UploadedFile $file, string $folder = 'identities'): ?string
    {
        $timestamp = (string) now()->timestamp;

        // Cloudinary signature: params sorted alphabetically, NOT URL-encoded
        $params = ['folder' => $folder, 'timestamp' => $timestamp];
        ksort($params);
        $sigString = urldecode(http_build_query($params, '', '&'));
        $signature  = sha1($sigString . $this->apiSecret);

        $endpoint = "https://api.cloudinary.com/v1_1/{$this->cloudName}/auto/upload";

        Log::info('Cloudinary upload attempt', [
            'folder'    => $folder,
            'endpoint'  => $endpoint,
            'sig_input' => $sigString,
            'filename'  => $file->getClientOriginalName(),
            'filesize'  => $file->getSize(),
        ]);

        $response = Http::attach(
            'file',
            $file->getContent(),
            $file->getClientOriginalName()
        )->post($endpoint, [
            'api_key'   => $this->apiKey,
            'timestamp' => $timestamp,
            'folder'    => $folder,
            'signature' => $signature,
        ]);

        if ($response->successful()) {
            $url = $response->json('secure_url');
            Log::info('Cloudinary upload success', ['url' => $url]);
            return $url;
        }

        Log::error('Cloudinary upload failed', [
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

        return null;
    }

    /**
     * Delete an asset from Cloudinary by public_id.
     * Returns true on success.
     */
    public function delete(string $publicId): bool
    {
        $timestamp = (string) now()->timestamp;
        $signature = sha1("public_id={$publicId}&timestamp={$timestamp}{$this->apiSecret}");

        $endpoint = "https://api.cloudinary.com/v1_1/{$this->cloudName}/image/destroy";

        $response = Http::post($endpoint, [
            'api_key'   => $this->apiKey,
            'public_id' => $publicId,
            'timestamp' => $timestamp,
            'signature' => $signature,
        ]);

        return $response->successful() && $response->json('result') === 'ok';
    }
}
