<?php

namespace App\Command;

use App\Entity\Series;
use App\Entity\Season;
use App\Entity\Episode;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use DateTime;
use GuzzleHttp\Client;

class AddSeriesCommand extends Command
{
    private $entityManager;
    private $serializer;

    public function __construct(EntityManagerInterface $entityManager, SerializerInterface $serializer)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
    }

    protected function configure()
    {
        $this->setName('app:add-series');
        $this->setDescription('Gets new series by name from the omdb api and adds them to the database.');
        $this->setHelp('This command allows you to get new series by name from the omdb api and add them to the database.');
        $this->setDefinition(
            new InputDefinition(array(
                new InputOption('series_name', 's', InputOption::VALUE_REQUIRED, 'Name of the series to search'),
            ))
        );
    }

    protected function fetchFromOmdb($query)
    {
        $client = HttpClient::create();
        $response = $client->request('GET', 'http://www.omdbapi.com', [
            'query' => $query
        ]);

        return $response->toArray();
    }

    protected function createSeries($data)
    {
        // create new series
        $series = new Series();
        $series->setTitle($data['Title']);
        $series->setPlot($data['Plot']);

        // retrieve start year
        $dateString = $data['Released'];
        $date = DateTime::createFromFormat("d M Y", $dateString);
        $yearStart = $date->format("Y");
        $series->setYearStart($yearStart);

        $series->setImdb($data['imdbID']);
        $series->setAwards($data['Awards']);

        // as trailer is not available with the api, there you go
        $series->setYoutubeTrailer("https://www.youtube.com/watch?v=dQw4w9WgXcQ");

        // retrieve poster
        $client = new Client();
        $image = $client->get($data['Poster'])->getBody()->getContents();
        $series->setPoster($image);

        return $series;
    }

    protected function createSeason($i, $series, $imdb, $output, $entityManager)
    {
        $season = new Season();
        $season->setNumber($i);
        $season->setSeries($series);
        $this->entityManager->persist($season);

        // ---- Episodes ----
        $episodes = $this->fetchFromOmdb([
            'apikey' => 'fed2a1ed',
            'type' => 'series',
            'Season' => $i,
            'i' => $imdb,
        ]);

        if ($episodes['Response'] == 'False' || !isset($episodes['Episodes'])) {
            $output->writeln("Couldn't find episodes in season" . $i);
            return;
        }

        // adds episodes to db
        for ($j = 0; $j < count($episodes['Episodes']); $j++) {
            $this->createEpisode($episodes['Episodes'][$j], $season, $entityManager);
        }
    }

    protected function createEpisode($data, $season, $entityManager)
    {
        $episode = new Episode();
        $episode->setNumber($data['Episode']);
        $episode->setTitle($data['Title']);
        $episode->setImdb($data['imdbID']);
        $episode->setImdbrating((float) $data['imdbRating']);

        $dateString = $data['Released'];
        if ($dateString != "N/A") {
            $date = DateTime::createFromFormat("Y-m-d", $dateString);
            $episode->setDate($date);
        }

        $episode->setSeason($season);
        $entityManager->persist($episode);

        return $episode;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $seriesName = $input->getOption('series_name');

        // fetch the OMDb API to get the series matching the search
        $responseData = $this->fetchFromOmdb([
            'apikey' => 'fed2a1ed',
            'type' => 'series',
            's' => $seriesName
        ]);

        // check if there is a response
        if ($responseData['Response'] == 'False') {
            $output->writeln("Couldn't find any series matching search");
            return Command::FAILURE;
        }

        // display series found
        $output->writeln("---- Series found ----");
        for ($i = count($responseData['Search']); $i >= 1; $i--) {
            $output->writeln("---- n°" . $i . "----");
            $output->writeln($responseData['Search'][$i - 1]['Title']);
            $output->writeln($responseData['Search'][$i - 1]['Year']);
            $output->writeln($responseData['Search'][$i - 1]['imdbID']);
        }

        // ask the user to choose a series
        $output->writeln("---- Choose a series ----");
        $helper = $this->getHelper('question');
        $question = new Question('Choose a series (by number): ');

        $seriesNumber = $helper->ask($input, $output, $question);

        // ---- Series ----
        $chosenSeries = $this->fetchFromOmdb([
            'apikey' => 'fed2a1ed',
            'type' => 'series',
            'i' => $responseData['Search'][$seriesNumber - 1]['imdbID']
        ]);

        if ($chosenSeries['Response'] == 'False' || $chosenSeries['totalSeasons'] == 'N/A') {
            $output->writeln("Couldn't find the series or it has no seasons");
            return Command::FAILURE;
        }

        $series = $this->createSeries($chosenSeries);

        // ---- Seasons ----
        $seasonsNum = $chosenSeries['totalSeasons'];
        for ($i = 1; $i <= $seasonsNum; $i++) {
            $season = $this->createSeason($i, $series, $responseData['Search'][$seriesNumber - 1]['imdbID'], $output, $this->entityManager);
        }

        $this->entityManager->persist($series);
        $this->entityManager->flush();

        $output->writeln('Series added successfully!');

        return Command::SUCCESS;
    }
}
