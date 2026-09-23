<?php

namespace App\Service;

use Aws\S3\S3Client;
use DateTime;
use App\Entity\Upload;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Doctrine\Common\Collections\Collection;

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

    /**
     * @param Collection<int, Upload> $uploads
     */
    public function removeUploads(Collection $uploads): void
    {
        $keys = $uploads->map(fn(Upload $upload) => $upload->getS3Key())->toArray();

        if (empty($keys)) {
            return;
        }

        $s3client = $this->createS3Client();
        $s3client->deleteObjects([
            'Bucket' => $this->bucket,
            'Delete' => [
                'Objects' => array_map(
                    fn(string $key) => ['Key' => $key],
                    $keys
                ),
            ],
        ]);
    }

    public function removeUpload(Upload $upload): void
    {
        $s3client = $this->createS3Client();
        $s3client->deleteObject([
            'Bucket' => $this->bucket,
            'Key'    => $upload->getS3Key(),
        ]);
    }

    /**
     * @param string[] $keys
     * @return array<string, Result> clé => existe
     */
    function findFilesByKeys(string $bucket, array $keys): array
    {
        $s3client = $this->createS3Client();
        $found = [];

        foreach ($keys as $key) {
            try {
                $response = $s3client->headObject(['Bucket' => $bucket, 'Key' => $key]);
                $found[$key] = $response;
            } catch (S3Exception $e) {
                if ($e->getStatusCode() !== 404) {
                    throw $e;
                }
                $found[$key] = false;
            }
        }

        return $found;
    }
}
