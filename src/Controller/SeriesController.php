<?php

namespace App\Controller;

use App\Entity\Episode;
use App\Entity\Genre;
use App\Entity\Series;
use App\Entity\User;
use App\Entity\Rating;
use App\Form\SeriesType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/series')]
class SeriesController extends AbstractController
{
    #[Route('/', name: 'app_series_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager, Request $request, PaginatorInterface $paginator): Response
    {
        // -- Pagination
        $query = $entityManager
            ->createQuery("SELECT ser FROM \App\Entity\Series ser
                           JOIN ser.genre g WITH g.name LIKE :genre
                           WHERE ser.title LIKE :search
                           ")
            ->setParameters([
                "search" => '%' . $request->query->get('search') . '%',
                'genre' => '%' . $request->query->get('genre') . '%'
            ]);

        if ($request->query->get('sort') == 'ser.avg_rating') {
            $direction = 'desc';
            if ($request->query->get('direction') == 'asc') {
                $direction = 'asc';
            }

            $request->query->set('sort', '');
            $query = $entityManager
                ->createQuery("SELECT ser FROM \App\Entity\Series ser
                                JOIN \App\Entity\Rating ratings WITH ratings.series = ser.id
                                JOIN ser.genre g WITH g.name LIKE :genre 
                                WHERE ser.title LIKE :search
                                GROUP BY ratings.id, ser.id
                                ORDER BY AVG(ratings.value)
                           " . $direction)
                ->setParameters([
                    'search' => '%' . $request->query->get('search') . '%',
                    'genre' => '%' . $request->query->get('genre') . '%'
                ]);
        }

        // $paginator->
        $pagination = $paginator->paginate(
            $query, /* query NOT result */
            $request->query->getInt('page', 1), /*page number*/
            10, /*limit per page*/
            [
                'defaultSortDirection' => $request->query->get('direction'),
            ]
        );

        // Grabbing the number of episodes for each series
        $series_total_epcount_map = $entityManager
            ->getRepository(Series::class)
            ->getSeriesTotalEpCount($pagination)
        ;

        return $this->render('series/index.html.twig', [
            'series' => $pagination,
            'series_epcount_map' => $series_total_epcount_map,
            'genres' => $entityManager->getRepository(Genre::class)->findAll(),
            'selected_genre' => $request->query->get('genre')
        ]);
    }

    #[Route('/adminseries', name: 'admin_series_index', methods: ['GET'])]
    public function adminSeries(EntityManagerInterface $entityManager, Request $request, PaginatorInterface $paginator): Response
    {
        // -- Pagination
        $dql   = "SELECT a FROM \App\Entity\Series a";
        $query = $entityManager->createQuery($dql);

        // Determine sort order from query string
        $sort_direction = $request->query->get('direction');

        $pagination = $paginator->paginate(
            $query, /* query NOT result */
            $request->query->getInt('page', 1), /*page number*/
            10, /*limit per page*/
            array('defaultSortDirection' => $sort_direction)
        );

        return $this->render('series/admin_series.html.twig', [
            'series' => $pagination,
        ]);
    }
    
    #[Route('/follow', name: 'app_series_follow', methods: ['GET', 'POST'])]
    public function followSeries(EntityManagerInterface $entityManager, Request $request): Response
    {
        // retrieve user
        $user = $this->getUser();

        // get series from id in request
        $series = $entityManager->getRepository(Series::class)->find($request->get('id'));
        $user->addSeries($series);

        // save changes
        $entityManager->persist($user);
        $entityManager->flush();

        // redirect user to previous location
        return $this->redirectToRoute('app_series_show', ['id' => $series->getId()]);
    }

    #[Route('/unfollow', name: 'app_series_unfollow', methods: ['GET', 'POST'])]
    public function unfollowSeries(EntityManagerInterface $entityManager, Request $request): Response
    {
        // retrieve user
        $user = $this->getUser();

        // get series from id in request
        $series = $entityManager->getRepository(Series::class)->find($request->get('id'));
        $user->removeSeries($series);

        // save changes
        $entityManager->persist($user);
        $entityManager->flush();

        // redirect user to previous location
        return $this->redirectToRoute('app_series_show', ['id' => $series->getId()]);
    }

    // display all series followed by user
    #[Route('/followed', name: 'app_series_followed', methods: ['GET'])]
    public function followedSeries(EntityManagerInterface $entityManager, Request $request, PaginatorInterface $paginator): Response
    {
        // retrieve user
        $user = $entityManager->getRepository(User::class)->find($request->get('id', $this->getUser()->getId()));

        // get series from id in request
        $pagination = $paginator->paginate(
            $user->getSeries(), /* query NOT result */
            $request->query->getInt('page', 1), /*page number*/
            10 /*limit per page*/
        );

        return $this->render('series/index.html.twig', [
            'series' => $pagination,
        ]);
    }

    #[Route('/watched', name: 'app_series_watched', methods: ['GET', 'POST'])]
    public function watchedEpisode(EntityManagerInterface $entityManager, Request $request): Response
    {
        // retrieve user
        $user = $this->getUser();

        // get episode from id in request
        $episode = $entityManager->getRepository(Episode::class)->find($request->get('id'));
        $user->addEpisode($episode);

        // save changes
        $entityManager->persist($user);
        $entityManager->flush();

        // redirect user to the current serie page
        return $this->redirectToRoute('app_series_show', ['id' => $episode->getSeason()->getSeries()->getId()]);
    }

    #[Route('/unwatched', name: 'app_series_unwatched', methods: ['GET', 'POST'])]
    public function unwatchedEpisode(EntityManagerInterface $entityManager, Request $request): Response
    {
        // retrieve user
        $user = $this->getUser();

        // get episode from id in request
        $episode = $entityManager->getRepository(Episode::class)->find($request->get('id'));
        $user->removeEpisode($episode);

        // save changes
        $entityManager->persist($user);
        $entityManager->flush();

        // redirect user to the current serie page
        return $this->redirectToRoute('app_series_show', ['id' => $episode->getSeason()->getSeries()->getId()]);
    }

    #[Route('/{id}', name: 'app_series_show', methods: ['GET'])]
    public function show(Series $series, Request $request, EntityManagerInterface $entityManager, int $id): Response
    {
        // is series followed by user
        $user = $this->getUser();
        $followed = false;

        // Get current filtering parameters
        $rating_min = 0; // default value
        $rating_max = 5; // default value
        if ($request->query->has('r_min')) {
            $rating_min = $request->query->get('r_min');
        }

        if ($request->query->has('r_max')) {
            $rating_max = $request->query->get('r_max');
        }
        
        // Grab the total number of episodes for this series
        $series_total_epcount_map = $entityManager
            ->getRepository(Series::class)
            ->getSeriesTotalEpCount($series)
        ;

        // Grab the ratings filtered by the selected range, or default ones
        $ratings = $entityManager
            ->getRepository(Rating::class)
            ->getApprovedAndNonLogicallyRatingsOfSeriesFilteredByRange($rating_min, $rating_max, $id);

        // Check if the current user is following this serie
        if ($user) {
            $followed = $user->getSeries()->contains($series);
        }

        $query = $entityManager
            ->createQuery("SELECT AVG(r.value) FROM \App\Entity\Rating r WHERE r.series=:series_id");

        $query->setParameter('series_id', $id);
        $average = $query->getSingleScalarResult();

        return $this->render('series/show.html.twig', [
            'series' => $series,
            'followed' => $followed,
            'series_epcount_map' => $series_total_epcount_map,
            'ratings' => $ratings,
            'average' => $average
        ]);
    }

    #[Route('/{id}/edit', name: 'app_series_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Series $series, EntityManagerInterface $entityManager): Response
    {
        $editSerieForm = $this->createForm(SeriesType::class, $series);
        $editSerieForm->handleRequest($request);

        if ($editSerieForm->isSubmitted() && $editSerieForm->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('admin_series_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('series/edit.html.twig', [
            'series' => $series,
            'editSerieForm' => $editSerieForm,
        ]);
    }

    #[Route('/{id}', name: 'app_series_delete', methods: ['POST'])]
    public function delete(Request $request, Series $series, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete' . $series->getId(), $request->request->get('_token'))) {
            $entityManager->remove($series);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_series_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/poster/{id}', name: 'app_series_poster', methods: ['GET'])]
    public function poster(Series $series): Response
    {
        $poster = $series->getPoster();
        return new Response(
            // read stream into a string
            stream_get_contents($poster),
            Response::HTTP_OK,
            ['content-type' => 'image/jpg']
        );
    }
}
