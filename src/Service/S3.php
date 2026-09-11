<?php

namespace App\Service;

use Aws\S3\S3Client;
use DateTime;

final class S3
{
    public function __construct(
        private string $endpoint,
        private string $region,
        private string $accessKey,
        private string $secretKey,
        private string $bucket,
    ) {}

    public function createS3Client(): S3Client
    {
        return new S3Client([
            'region' => $this->region,
            'endpoint' => $this->endpoint,
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => $this->accessKey,
                'secret' => $this->secretKey,
            ],
        ]);
    }

    public function createPutPresignUrl(string $key, string $mimeType): string
    {
        $s3client = $this->createS3Client();
        $expiration = new DateTime("+20 minutes");

        $command = $s3client->getCommand('PutObject', [
            'Bucket' => $this->bucket,
            'Key' => $key,
            'ContentType' => $mimeType,
        ]);

        $request = $s3client->createPresignedRequest($command, $expiration);

        return (string) $request->getUri();
    }

    public function createGetPresignUrl(string $key): string
    {
        $s3client = $this->createS3Client();
        $expiration = new DateTime("+20 minutes");

        $command = $s3client->getCommand('GetObject', [
            'Bucket' => $this->bucket,
            'Key' => $key,
        ]);

        $request = $s3client->createPresignedRequest($command, $expiration);

        return (string) $request->getUri();
    }
}
