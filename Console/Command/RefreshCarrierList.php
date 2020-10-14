<?php 
namespace Shiptimize\Shipping\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RefreshCarrierList extends Command
{ 
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    public function __construct(\Magento\Framework\ObjectManagerInterface $objectManager){
        $this->objectManager = $objectManager; 
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('shiptimize:refreshcarrierlist')->setDescription('Refresh Token and Carrier settings');
        parent::configure();
    }

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return null|int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Shiptimize After install.</info>');
        $shiptimizeMagento = $this->objectManager->create('Shiptimize\Shipping\Model\ShiptimizeMagento');
        $shiptimizeMagento->refreshToken();  
    }
}