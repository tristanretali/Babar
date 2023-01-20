<?php

namespace App\Command;

use App\Entity\User;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

// Adds a new admin account given a username, email and password
class AddAdminCommand extends Command
{
    protected static $defaultName = 'app:add-admin';
    private $entityManager;
    private $passwordEncoder;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->passwordEncoder = $passwordHasher;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');

        // asks username
        $question = new Question('Enter username: ');
        $username = $helper->ask($input, $output, $question);

        // asks email
        $question = new Question('Enter email: ');
        $email = $helper->ask($input, $output, $question);

        // asks password and hash it
        $question = new Question('Enter password: ');
        $password = $helper->ask($input, $output, $question);
        $hashedPassword = $this->passwordEncoder->hashPassword(new User(), $password);

        // create a new entity object
        $entity = new User();
        $entity->setName($username);
        $entity->setEmail($email);
        $entity->setPassword($hashedPassword);
        $entity->setAdmin(true);
        $entity->setRegisterDate(new \DateTime('now'));

        // write changes to database
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $output->writeln('New admin user successfully added!');

        return Command::SUCCESS;
    }
}
