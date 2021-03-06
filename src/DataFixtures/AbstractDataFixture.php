<?php

namespace App\DataFixtures;

use App\Entity\AbstractEntity;
use App\Entity\Building;
use App\Entity\Campus;
use App\Entity\Course;
use App\Entity\Instructor;
use App\Entity\Room;
use App\Entity\Section;
use App\Entity\Subject;
use App\Entity\Term;
use App\Entity\TermBlock;
use App\Helpers\ImportDriverHelper;
use App\Util\AbstractImportDriver;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Abstract class for the data fixtures to extend.
 * 
 * @author Austin Shinpaugh
 */
abstract class AbstractDataFixture extends Fixture implements FixtureInterface, OrderedFixtureInterface, ContainerAwareInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;
    
    /**
     * @var String[]
     */
    protected $location;
    
    /**
     * @var String
     */
    protected $service_id;
    
    /**
     * @var OutputInterface
     */
    protected static $_output;
    
    /**
     * Load data fixtures with the passed EntityManager
     *
     * @param ObjectManager $manager
     */
    abstract public function load(ObjectManager $manager);
    
    /**
     * {@inheritdoc}
     */
    abstract public function getOrder();
    
    /**
     * {@inheritdoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $helper = $container->get(ImportDriverHelper::class);
        
        $this->container  = $container;
        $this->service_id = $helper->getServiceId();
    }
    
    /**
     * Get the ConsoleOutput object from the console command.
     * 
     * @return ConsoleOutput|OutputInterface
     */
    public static function getOutput()
    {
        if (static::$_output) {
            return static::$_output;
        }
        
        return (static::$_output = new ConsoleOutput(
            ConsoleOutput::VERBOSITY_DEBUG
        ));
    }
    
    /**
     * Create a ProgressBar object.
     * 
     * @param int $max
     *
     * @return ProgressBar
     */
    public function getProgressBar($max = 0)
    {
        $progress = new ProgressBar(static::getOutput(), $max);
        $progress->setFormat('debug');
        $progress->setRedrawFrequency(100);
        
        return $progress;
    }
    
    /**
     * Get the import utility.
     * 
     * @param boolean $reset Reset the import entries's array pointer.
     * 
     * @return AbstractImportDriver
     */
    protected function getImporter($reset = false)
    {
        $helper   = $this->container->get(ImportDriverHelper::class);
        $importer = $this->container->get($helper->getServiceId());
        
        if ($reset) {
            $importer->firstEntry();
        }
        
        return $importer;
    }
    
    /**
     * Return a campus object.
     * 
     * @return AbstractEntity|Campus
     */
    protected function getCampus()
    {
        $campus = $this->getImporter()->createCampus();
        $key    = $this->getKey($campus);
        
        if ($obj = $this->getReference($key)) {
            return $obj;
        }
        
        return $this->store($campus, $key);
    }
    
    /**
     * Returns an instance of the building object.
     *
     * @param Campus|null $campus
     *
     * @return AbstractEntity|Building
     */
    protected function getBuilding(Campus $campus = null)
    {
        $campus   = $campus ?: $this->getCampus();
        $building = $this->getImporter()->createBuilding($campus);
        $key      = $this->getKey($building);
        
        if ($obj = $this->getReference($key)) {
            return $obj;
        }
        
        $campus->addBuilding($building);
        
        return $this->store($building, $key);
    }
    
    /**
     * Get a room object.
     * 
     * @param Building|null $building
     *
     * @return AbstractEntity|Room
     */
    protected function getRoom(Building $building = null)
    {
        $building = $building ?: $this->getBuilding();
        $room     = $this->getImporter()->createRoom($building);
        $room_key = $this->getKey($room);
        
        if ($obj = $this->getReference($room_key)) {
            return $obj;
        }
        
        $building->addRoom($room);
        
        return $this->store($room, $room_key);
    }
    
    /**
     * Return an instructor object.
     * 
     * @return AbstractEntity|Instructor
     */
    protected function getInstructor()
    {
        $instructor = $this->getImporter()->createInstructor();
        $key        = $this->getKey($instructor);
        
        if ($obj = $this->getReference($key)) {
            return $obj;
        }
        
        return $this->store($instructor, $key);
    }
    
    /**
     * Fetch the term block.
     * 
     * @return AbstractEntity|TermBlock
     */
    protected function getTerm()
    {
        $block     = $this->getImporter()->createTerm();
        $block_key = $this->getKey($block);
        
        if ($obj = $this->getReference($block_key)) {
            // TermBlock already exists.
            return $obj;
        }
        
        $term_key = $this->getKey($block->getTerm());
        
        if (!$term = $this->getReference($term_key)) {
            // First time the term was created. Store it.
            $this->store(($term = $block->getTerm()), $term_key);
        }
        
        $term->addBlock($block);
        
        return $this->store($block, $block_key);
    }
    
    /**
     * Get the subject object.
     * 
     * @return AbstractEntity|Subject
     */
    protected function getSubject()
    {
        $subject = $this->getImporter()->createSubject();
        $key     = $this->getKey($subject);
        
        if ($obj = $this->getReference($key)) {
            return $obj;
        }
        
        return $this->store($subject, $key);
    }
    
    /**
     * @param Subject|null $subject
     *
     * @return AbstractEntity|Course
     */
    protected function getCourse(Subject $subject = null)
    {
        $subject = $subject ?: $this->getSubject();
        $course  = $this->getImporter()->createCourse($subject);
        $key     = $this->getKey($course);
        
        if ($obj = $this->getReference($key)) {
            return $obj;
        }
        
        $subject->addCourse($course);
        
        return $this->store($course, $key);
    }
    
    protected function getSection(Course $course = null)
    {
        $course  = $course ?: $this->getCourse();
        $section = $this->getImporter()->createSection();
        $room    = $this->getRoom();
        
        $section
            ->setCampus($this->getCampus())
            ->setBuilding($room->getBuilding())
            ->setRoom($room)
            ->setBlock($this->getTerm())
            ->setInstructor($this->getInstructor())
            ->setSubject($this->getSubject())
            ->setCourse($course)
        ;
        
        $course->addSection($section);
        
        $this->getDoctrine()->getManager()->persist($section);
        
        return $section;
    }
    
    /**
     * Get a string representation of an object so that it may be fetched later.
     * 
     * @param AbstractEntity $object
     *
     * @return string
     */
    protected function getKey($object)
    {
        if ($object instanceof Campus) {
            return 'c-' . $object->getShortName();
        }
        
        if ($object instanceof Building) {
            return $this->getKey($object->getCampus()) . '_b-' . $object->getShortname();
        }
        
        if ($object instanceof Room) {
            return $this->getKey($object->getBuilding()) . '_r-' . $object->getNumber();
        }
        
        if ($object instanceof Instructor) {
            return 'i-' . $object->getId();
        }
        
        if ($object instanceof Term) {
            return $object->getYear() . '-' . $object->getSemester();
        }
        
        if ($object instanceof TermBlock) {
            return $this->getKey($object->getTerm()) . '_b-' . $object->getShortName();
        }
        
        if ($object instanceof Subject) {
            return 'sub-' . $object->getName();
        }
        
        if ($object instanceof Section) {
            return $this->getKey($object->getBlock()) . '_sec-' . $object->getCrn();
        }
        
        if ($object instanceof Course) {
            return $this->getKey($object->getSubject()) . " ({$object->getNumber()})";
        }
        
        throw new \LogicException('[AbstractDataFixture::getKey] Missing pattern for: ' . get_class($object));
    }
    
    /**
     * {@inheritdoc}
     */
    public function getReference($name)
    {
        try {
            $value = parent::getReference($name);
        } catch (\OutOfBoundsException $e) {
            $value = null;
        }
        
        return $value;
    }
    
    /**
     * Register the entity with the ObjectManager and add a reference to it.
     * 
     * @param AbstractEntity $entity
     * @param String         $key
     *
     * @return AbstractEntity
     */
    protected function store(AbstractEntity $entity, $key)
    {
        $this->addReference($key, $entity);
        
        $this->getDoctrine()
            ->getManager()
            ->persist($entity)
        ;
        
        return $entity;
    }
    
    /**
     * @return ManagerRegistry
     */
    protected function getDoctrine()
    {
        return $this->container->get('doctrine');
    }
}
