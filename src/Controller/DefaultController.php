<?php


namespace App\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{
    /**
     * Main controller only to show the index page
     *
     * @Route("/", name="app_index")
     * @return Response
     */
    public function index()
    {
        return $this->render('base.html.twig', []);
    }
}