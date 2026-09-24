<?php

namespace App\Service;

use App\Entity\Upload;
use App\Enum\StatusEnum;
use App\Repository\UploadRepository;
use Doctrine\ORM\EntityManagerInterface;

class UploadService
{
    public function __construct(private UploadRepository $uploadRepository, private S3 $s3, private EntityManagerInterface $em) {}


    public function checkIfFilesWereUploaded(): void
    {
        $uploadsToCheck = $this->uploadRepository->getOldPendingUploads();

        if (empty($uploadsToCheck)) {
            return;
        }

        $keys = array_map(fn(Upload $upload) => $upload->getS3Key(), $uploadsToCheck);

        $foundKeys = $this->s3->findFilesByKeys('bucket-1', $keys);

        foreach ($foundKeys as  $s3Key => $result) {
            $upload = $this->uploadRepository->findOneBy(['s3Key' => $s3Key]);

            if (!$upload) {
                continue;
            }

            if (!$result) {
                $this->em->remove($upload);
                continue;
            }

            $upload->setStatus(StatusEnum::UPLOADED);
            $upload->setEtag($result->get('ETag'));
            $upload->setSizeBytes($result->get('ContentLength'));
        }

        $this->em->flush();
    }

    public function deleteS3Orphan(): void
    {
        $s3Content = $this->s3->getAllS3KeyWithDateInBucket();

        $uploads = $this->uploadRepository->getAllS3key();

        $toDelete = [];
        $weekOldDate = (new \DateTimeImmutable('now'))->modify('-7 days');

        foreach ($s3Content as $s3Key => $date) {
            if (isset($uploads[$s3Key])) {
                continue;
            }

            if ($date > $weekOldDate) {
                continue;
            }

            $toDelete[] = $s3Key;
        }

        $this->s3->removeFileByKey($toDelete);
    }
}
