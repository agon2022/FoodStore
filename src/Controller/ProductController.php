<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Order;
use App\Entity\OrderDetail;
use App\Entity\Category;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use App\Repository\OrderDetailRepository;
use App\Repository\OrderRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Doctrine\Common\Collections\Criteria;

/**
 * @Route("/product")
 */
class ProductController extends AbstractController
{
    /**
     * @Route("/list/{pageId}", name="app_product_index", methods={"GET"})
     */
    public function index(Request $request, ProductRepository $productRepository,
    CategoryRepository $categoryRepository,
    int $pageId = 1): Response
    {
        //$this->denyAccessUnlessGranted('ROLE_USER');
        $minPrice = $request->query->get('minPrice');
        $maxPrice = $request->query->get('maxPrice');
        $Cat = $request->query->get('category');
        $word = $request->query->get('name');
        $orderby = $request->query->get('orderBy');
        $sortBy = $request->query->get('sortBy');

        
        if(!(is_null($Cat)||empty($Cat))){
            $selectedCat=$Cat;
        }
        else
        $selectedCat='';


        $tempQuery = $productRepository->findMore($minPrice, $maxPrice, $Cat,$word,$sortBy,$orderby);
        $pageSize = 4;

    // load doctrine Paginator
        $paginator = new Paginator($tempQuery);

    // you can get total items
        $totalItems = count($paginator);

    // get total pages
        $numOfPages = ceil($totalItems / $pageSize);

    // now get one page's items:
        $tempQuery = $paginator
        ->getQuery()
        ->setFirstResult($pageSize * ($pageId - 1)) // set the offset
        ->setMaxResults($pageSize); // set the limit


        return $this->render('product/index.html.twig', [
            'products' =>  $tempQuery->getResult(),
            'selectedCat' => $selectedCat,
            'numOfPages' => $numOfPages,
        ]);
    }

        /**
 * @Route("/addCart/{id}", name="app_add_cart", methods={"GET"})
 */
public function addCart(Product $product, Request $request)
{
    $session = $request->getSession();
    $quantity = (int)$request->query->get('quantity');

    //check if cart is empty
    if (!$session->has('cartElements')) {
        //if it is empty, create an array of pairs (prod Id & quantity) to store first cart element.
        $cartElements = array($product->getId() => $quantity);
        //save the array to the session for the first time.
        $session->set('cartElements', $cartElements);
    } else {
        $cartElements = $session->get('cartElements');
        //Add new product after the first time. (would UPDATE new quantity for added product)
        $cartElements = array($product->getId() => $quantity) + $cartElements;
        //Re-save cart Elements back to session again (after update/append new product to shopping cart)
        $session->set('cartElements', $cartElements);
    }
    return $this->redirectToRoute('app_product_index', ['id'=> $product->getId()], Response::HTTP_SEE_OTHER); //means 200, successful
}

         /**
    * @Route("/reviewCart", name="app_review_cart", methods={"GET"})
    */
    public function cart(Request $request, ProductRepository $productRepository){
        $total = 0;
        $session = $request->getSession();
        $cart = $session->get('cartElements',[]);
        $cartWithData = [];
        foreach ($cart as $id => $quantity){
            $cartWithData[] = [
                'product' => $productRepository->find($id),
                'quantity' => $quantity
            ];
        }
        foreach ($cartWithData as $item){
            $totalItem = $item['product']->getPrice() * $item['quantity'];
            $total += $totalItem;
        }
        return $this->render('cart/cart.html.twig',[
            'items' => $cartWithData,
            'total' => $total
        ]);
    }

       /**
    * @Route("/checkoutCart", name="app_checkout_cart", methods={"GET"})
    */
    public function checkoutCart(Request               $request,
    OrderDetailRepository $orderDetailRepository,
    OrderRepository       $orderRepository,
    ProductRepository     $productRepository,
    ManagerRegistry       $mr): Response
    {
    $this->denyAccessUnlessGranted('ROLE_USER');
    $entityManager = $mr->getManager();
    $session = $request->getSession(); //get a session
    // check if session has elements in cart
    if ($session->has('cartElements') && !empty($session->get('cartElements'))) {
    try {
    // start transaction!
    $entityManager->getConnection()->beginTransaction();
    $cartElements = $session->get('cartElements');

    //Create new Order and fill info for it. (Skip Total temporarily for now)
    $order = new Order();
    date_default_timezone_set('Asia/Ho_Chi_Minh');
    $order->setPurchaseDate(new \DateTime());
    /** @var \App\Entity\User $user */
    $user = $this->getUser();
    $order->setUser($user);
    $orderRepository->add($order, true); //flush here first to have ID in Order in DB.

    //Create all Order Details for the above Order
    $total = 0;
    foreach ($cartElements as $product_id => $quantity) {
    $product = $productRepository->find($product_id);
    //create each Order Detail
    $orderDetail = new OrderDetail();
    $orderDetail->setOrders($order);
    $orderDetail->setProduct($product);
    $orderDetail->setQuantity($quantity);
    $orderDetailRepository->add($orderDetail);

    $total += $product->getPrice() * $quantity;
    }
    $order->setTotal($total);
    $orderRepository->add($order);
    // flush all new changes (all order details and update order's total) to DB
    $entityManager->flush();

    // Commit all changes if all changes are OK
    $entityManager->getConnection()->commit();

    // Clean up/Empty the cart data (in session) after all.
    $session->remove('cartElements');
    } catch (Exception $e) {
    // If any change above got trouble, we roll back (undo) all changes made above!
    $entityManager->getConnection()->rollBack();
    }
    return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
    } else
    return new Response("Shopping Cart has no product, please add some!");
    }


    /**
     * @Route("/new", name="app_product_new", methods={"GET", "POST"})
     */
    public function new(Request $request, ProductRepository $productRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SELLER');
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);
        $user = $this->getUser();
        if ($form->isSubmitted() && $form->isValid()) {
            $productImg = $form->get('Image')->getData();
            $product->setPublisher($user);
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            if ($productImg) {
                $originExt = pathinfo($productImg->getClientOriginalName(), PATHINFO_EXTENSION);
                $newFileName = $product->getId() . '.' . $originExt;

                try {
                    $productImg->move(
                        $this->getParameter('product_directory'),
                        $newFileName
                    );
                } catch (FileException $e) {
                }
                $product->setImage($newFileName);
            }

            $productRepository->add($product, true);

            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('product/new.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }
    /**
     * @Route("/{id}", name="app_product_show", methods={"GET"})
     */
    public function show(Product $product): Response
    {
        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="app_product_edit", methods={"GET", "POST"})
     */
    public function edit(Request $request, Product $product, ProductRepository $productRepository): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);
        $this->denyAccessUnlessGranted('ROLE_CUSTOMER');
        if ($form->isSubmitted() && $form->isValid()) {
            $productRepository->add($product, true);

            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('product/edit.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    /**
     * @Route("/{id}", name="app_product_delete", methods={"POST"})
     */
    public function delete(Request $request, Product $product, ProductRepository $productRepository): Response
    {
        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->request->get('_token'))) {
            $productRepository->remove($product, true);
        }

        return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
    }
    /**
 * @Route("/setRole", name="app_set_role", methods={"GET"})
 */
public function setRole(UserRepository $userRepository): JsonResponse
{
    /** @var \App\Entity\User $user */
    $user = $this->getUser();
    $user->setRoles(array('ROLE_ADMIN'));
    $userRepository->add($user, true);
    return $this->json(['username' => $this->getUser()->getUserIdentifier()]);
}

}
