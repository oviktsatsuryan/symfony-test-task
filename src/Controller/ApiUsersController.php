<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class ApiUsersController extends AbstractController
{
    #[Route('/api/users/query', name: 'app_api_query_users', methods:['GET'])]
    public function query(
        Request $request,
        ManagerRegistry $doctrine
    ): JsonResponse
    {
        $response = ['error'=>false];

        $usernameKeyword = trim($request->get('username'));
        $emailKeyword = trim($request->get('email'));

        if (!$usernameKeyword && !$emailKeyword) {
            $response['error'] = true;
            $response['errors'] = ['Please specify at least one search criteria'];
            return $this->json($response);
        }

        $queryBuilder = $doctrine->getManager()->createQueryBuilder();
        // Пояснение: чтобы заранее не определять присутствие одного из параметров и не плодить вложенные ифы,
        // устанавливаем первичный параметр выборки невозможным
        $query = $queryBuilder->select(['u.id','u.username','u.email'])->from(User::class,'u')->where('1 = 2');


        if ($usernameKeyword) {
            $query->orWhere("u.username = :username")->setParameter("username",$usernameKeyword);
        }
        if ($emailKeyword) {
            $query->orWhere("u.email = :email")->setParameter("email",$emailKeyword);
        }

        $users = $query->getQuery()->getResult();

        $response['data'] = $users;

        return $this->json($response);
    }
    #[Route('/api/users', name: 'app_api_all_users', methods:['GET'])]
    public function all(
        Request $request,
        ManagerRegistry $doctrine
    ): JsonResponse
    {

        $allUsers = $doctrine->getManager()->getRepository(User::class)->findAll();

        $normalizer = new ObjectNormalizer();
        $serializer = new Serializer([$normalizer]);
        return $this->json([
            'error' => false,
            'data'  => $serializer->normalize($allUsers,null,[AbstractNormalizer::ATTRIBUTES =>['id','username','email']])
        ]);
    }
}
