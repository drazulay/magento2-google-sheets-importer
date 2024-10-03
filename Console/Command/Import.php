<?php

namespace SolidBase\GoogleSheetsImporter\Console\Command;

use Google\Service\Exception;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use SolidBase\GoogleSheetsImporter\Service\Importer;
use Symfony\Component\Console\Command\Command;

class Import extends Command
{
    private Importer $importer;
    private State $appState;

    public function __construct(Importer $importer, State $appState)
    {
        $this->importer = $importer;
        $this->appState = $appState;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('google-sheets:import');
        $this->setDescription('Import data from Google Sheets');

        $this->appState->setAreaCode(Area::AREA_GLOBAL);
    }

    /**
     * @throws Exception
     * @throws FileSystemException
     * @throws \Google\Exception
     * @throws LocalizedException
     */
    protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output)
    {
        $this->importer->run();

        return 0;
    }
}
