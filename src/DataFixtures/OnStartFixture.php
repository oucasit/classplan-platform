<?php

namespace App\DataFixtures;

use App\Entity\UpdateLog;
use App\Helpers\ImportDriverHelper;
use App\Util\OdsImportDriver;
use Doctrine\Persistence\ObjectManager;

/**
 * First fixture loaded - do some init the UpdateLog.
 * 
 * @author Austin Shinpaugh
 */
class OnStartFixture extends AbstractDataFixture
{
    /**
     * {@inheritdoc}
     */
    public function load(ObjectManager $manager)
    {
        $helper = $this->container->get(ImportDriverHelper::class);
        $import = $this->getImporter();
        $source = 'book';
        
        if ($import instanceof OdsImportDriver) {
            $source = 'ods';
        }
        
        foreach ($helper->getUpdateLogs() as $log) {
            $manager->persist($log);
        }
        
        $manager->persist(new UpdateLog($source));
        $manager->flush();
        
        // Free up memory.
        $helper->clearLogs();

        $import->init();
    }
    
    /**
     * {@inheritdoc}
     */
    public function getOrder()
    {
        return 1;
    }
}
