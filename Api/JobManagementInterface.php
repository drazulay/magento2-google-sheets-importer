<?php

namespace SolidBase\GoogleSheetsImporter\Api;

use SolidBase\GoogleSheetsImporter\Api\Data\JobInterface;

interface JobManagementInterface
{
    public function getJobs(): array;

    public function queueJob(JobInterface $job): JobManagementInterface;

    public function removeJob(JobInterface $job): JobManagementInterface;

}
