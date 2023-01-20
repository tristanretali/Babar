<?php

namespace App\Controller;

use App\Entity\Rating;
use App\Entity\Series;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/rating')]
class RatingController extends AbstractController
{
    #[Route('/new', name: 'app_rating_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $rating = new Rating();

        $value = $request->get('value');
        $comment = $request->get('comment');
        $user = $this->getUser();
        $series = $entityManager->getRepository(Series::class)->find($request->get('id'));

        //prevent user from sending incorrect rate value
        if ($value < 1 || $value > 5) {
            return $this->redirectToRoute('app_series_show', ['id' => $series->getId()]);
        }

        //prevent user from sending more than one review
        if (count($entityManager->getRepository(Rating::class)->findBy(array('user' => $user, 'series' => $series))) != 0) {
            return $this->redirectToRoute('app_series_show', ['id' => $series->getId()]);
        }

        $rating->setValue($value);
        $rating->setComment($comment);
        $rating->setUser($user);
        $rating->setDate(new \DateTime());
        $rating->setSeries($series);
        $rating->setDeleted(false);
        $rating->setReported(false);
        $rating->setApproved(false);

        // save changes
        $entityManager->persist($rating);
        $entityManager->flush();

        return $this->redirectToRoute('app_series_show', ['id' => $series->getId()]);
    }

    #[Route('/{id}/edit', name: 'app_rating_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Rating $rating, EntityManagerInterface $entityManager): Response
    {
        $value = $request->get('value');
        $comment = $request->get('comment');
        $rating = $entityManager->getRepository(Rating::class)->find($request->get('id'));
        $series = $entityManager->getRepository(Series::class)->find($request->get('series_id'));

        //prevent user from sending incorrect rate value
        if ($value < 1 || $value > 5) {
            return $this->redirectToRoute('app_series_show', ['id' => $series->getId()]);
        }

        $rating->setValue($value);
        $rating->setComment($comment);
        $rating->setDate(new \DateTime());
        $rating->setApproved(false);
        $rating->setDeleted(false);

        //save changes
        $entityManager->persist($rating);
        $entityManager->flush();

        return $this->redirectToRoute('app_series_show', ['id' => $series->getId()]);
    }

    #[Route('/{id}', name: 'app_rating_delete', methods: ['POST'])]
    public function delete(Request $request, Rating $rating, EntityManagerInterface $entityManager): Response
    {
        $seriesID = $request->get('series_id');

        if ($this->isCsrfTokenValid('delete' . $rating->getId(), $request->request->get('_token'))) {
            $entityManager->remove($rating);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_series_show', ['id' => $seriesID], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/delete', name: 'app_rating_delete_logical', methods: ['POST'])]
    public function deleteLogical(Request $request, Rating $rating, EntityManagerInterface $entityManager): Response
    {
        $seriesID = $request->get('series_id');

        if ($this->isCsrfTokenValid('delete' . $rating->getId(), $request->request->get('_token'))) {
            $rating->setDeleted(true);
            $entityManager->flush();
        }

        if ($seriesID == null) {
            return $this->redirectToRoute('app_rating_show_reported', [], Response::HTTP_SEE_OTHER);
        } else {
            return $this->redirectToRoute('app_series_show', ['id' => $seriesID], Response::HTTP_SEE_OTHER);
        }
    }

    #[Route('/{id}/report', name: 'app_rating_report', methods: ['POST'])]
    public function report(Request $request, Rating $rating, EntityManagerInterface $entityManager): Response
    {
        $seriesID = $request->get('series_id');

        if ($this->isCsrfTokenValid('report' . $rating->getId(), $request->request->get('_token'))) {
            $rating->setReported(true);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_series_show', ['id' => $seriesID], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/unreport', name: 'app_rating_unreport', methods: ['POST'])]
    public function unreport(Request $request, Rating $rating, EntityManagerInterface $entityManager): Response
    {
        $seriesID = $request->get('series_id');

        if ($this->isCsrfTokenValid('unreport' . $rating->getId(), $request->request->get('_token'))) {
            $rating->setReported(false);
            $entityManager->flush();
        }

        if ($seriesID == null) {
            return $this->redirectToRoute('app_rating_show_reported', [], Response::HTTP_SEE_OTHER);
        } else {
            return $this->redirectToRoute('app_series_show', ['id' => $seriesID], Response::HTTP_SEE_OTHER);
        }
    }

    #[Route('/{id}/approve', name: 'app_rating_approve', methods: ['POST'])]
    public function approve(Request $request, Rating $rating, EntityManagerInterface $entityManager): Response
    {
        $seriesID = $request->get('series_id');

        if ($this->isCsrfTokenValid('approve' . $rating->getId(), $request->request->get('_token'))) {
            $rating->setApproved(true);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_rating_show_unapproved', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/unapprove', name: 'app_rating_unapprove', methods: ['POST'])]
    public function unapprove(Request $request, Rating $rating, EntityManagerInterface $entityManager): Response
    {
        $seriesID = $request->get('series_id');

        if ($this->isCsrfTokenValid('unapprove' . $rating->getId(), $request->request->get('_token'))) {
            $rating->setApproved(false);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_rating_show_unapproved', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/reported', name: 'app_rating_show_reported', methods: ['GET'])]
    public function showReported(Request $request, EntityManagerInterface $entityManager): Response
    {
        $ratings = $entityManager->getRepository(Rating::class)->findBy(array('reported' => true), array('date' => 'DESC'));

        return $this->render('rating/reported.html.twig', [
            'ratings' => $ratings,
        ]);
    }

    #[Route('/unapproved', name: 'app_rating_show_unapproved', methods: ['GET'])]
    public function showUnapproved(Request $request, EntityManagerInterface $entityManager): Response
    {
        $ratings = $entityManager->getRepository(Rating::class)->findBy(array('approved' => false), array('date' => 'DESC'));

        return $this->render('rating/unapproved.html.twig', [
            'ratings' => $ratings,
        ]);
    }
}
