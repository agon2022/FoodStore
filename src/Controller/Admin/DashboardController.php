<?php

namespace App\Controller\Admin;
use App\Entity\User;
use App\Entity\Product;
use App\Entity\Category;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractDashboardController
{
    /**
     * @Route("/admin", name="admin")
     */
    public function index(): Response
    {
        $checkUser = $this->isGranted('ROLE_SELLER');
        if (!$checkUser)
        {
            return $this->redirectToRoute('app_login', [], Response::HTTP_SEE_OTHER);
        }  
        return parent::index();
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Admin');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToCrud('User', 'fas fa-user', User::class);
        yield MenuItem::linkToCrud('Category', 'fas fa-category', Category::class);
        yield MenuItem::linkToCrud('Product', 'fas fa-product', Product::class);
    }
}
