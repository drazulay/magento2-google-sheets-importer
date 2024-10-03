<?php

namespace SolidBase\GoogleDriveImporter\Api;

use SolidBase\GoogleDriveImporter\Api\Data\JobInterface;

interface JobManagementInterface
{
    public function getJobs(): array;

    public function queueJob(JobInterface $job): JobManagementInterface;

    public function removeJob(JobInterface $job): JobManagementInterface;

}
