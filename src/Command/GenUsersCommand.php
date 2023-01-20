<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\Series;
use App\Entity\Rating;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

// Adds new users given a username, email and password, and a number of users to generate
class GenUsersCommand extends Command
{
    protected static $defaultName = 'app:gen-users';
    private $entityManager;
    private $passwordEncoder;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->passwordEncoder = $passwordHasher;
    }

    public function configure()
    {
        $this->setName('app:gen-users');
        $this->setDescription('Generates a given number of users and a given number of ratings per user');
        $this->setHelp('This command allows you to generate a given number of users, and a given number of ratings per user');
        $this->setDefinition(
            new InputDefinition(array(
                new InputOption('users_num', 'u', InputOption::VALUE_REQUIRED, 'Number of users to generate'),
                new InputOption('ratings_num', 'r', InputOption::VALUE_OPTIONAL, 'Number of ratings per user', 0),
            ))
        );
    }

    protected function randNormalInt($mean, $stddev)
    {
        $x = mt_rand() / mt_getrandmax();
        $y = mt_rand() / mt_getrandmax();
        $rand_normal =  $mean + sqrt(-2 * log($x)) * cos(2 * pi() * $y) * $stddev;
        return min(max(round($rand_normal), 1), 5);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ipsum = "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.";
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

        // get the number of users and ratings to generate
        $usersNum = (int) $input->getOption('users_num');
        $ratingsNum = (int) $input->getOption('ratings_num');

        // create the given number of users
        for ($i = 0; $i < $usersNum; $i++) {
            $entity = new User();
            $entity->setName($username . $i + 1);

            $atPos = strpos($email, '@');
            $newEmail = substr_replace($email, $i + 1, $atPos, 0);

            $entity->setEmail($newEmail);
            $entity->setPassword($hashedPassword);
            $entity->setAdmin(false);
            $entity->setRegisterDate(new \DateTime('now'));

            // create the given number of ratings per user
            for ($j = 0; $j < $ratingsNum; $j++) {
                // get a random series from the database
                $randomSeries = $this->entityManager
                    ->getRepository(Series::class)
                    ->createQueryBuilder('s')
                    ->getQuery()
                    ->getResult();
                shuffle($randomSeries);
                $randomSeries = $randomSeries[0];

                // create a new rating
                $rating = new Rating();
                $rating->setUser($entity);
                $rating->setSeries($randomSeries);
                $rating->setValue($this->randNormalInt(3, 1));
                $rating->setComment($ipsum);
                $rating->setDate(new \DateTime('now'));
                $rating->setDeleted(0);
                $rating->setApproved(1);
                $rating->setReported(0);

                // persist changes
                $this->entityManager->persist($rating);
            }

            // persist changes
            $this->entityManager->persist($entity);
        }

        // write changes to database
        $this->entityManager->flush();

        $output->writeln('New user accounts successfully created!');

        return Command::SUCCESS;
    }
}
