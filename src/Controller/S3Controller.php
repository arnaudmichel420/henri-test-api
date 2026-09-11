<?php

namespace App\Controller;

use App\Entity\Upload;
use App\Service\S3;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class S3Controller extends AbstractController
{

    #[Route('/api/uploads/{id}/download-url', methods: ['GET'])]
    public function getDownloadUrl(
        Upload $upload,
        S3 $s3
    ): JsonResponse {
        $url = $s3->createGetPresignUrl($upload->getS3Key());

        return $this->json(['url' => $url], 200);
    }

    #[Route('/api/uploads/{id}/upload-url', methods: ['POST'])]
    public function getUploadUrl(
        Upload $upload,
        S3 $s3
    ): JsonResponse {
        $url = $s3->createPutPresignUrl($upload->getS3Key(), $upload->getMimeType());

        return $this->json(['url' => $url], 200);
    }
}
