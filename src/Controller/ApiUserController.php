<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ApiUserController extends AbstractController
{

    #[Route('/api/user', name: 'api_create_user', methods:['POST'])]
    public function create(
        ManagerRegistry $doctrine,
        Request $request,
        ValidatorInterface $validator,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse
    {
        $entityManager = $doctrine->getManager();

        $user = new User();

        $email = trim($request->get('email'));
        $userName = trim($request->get('username'));
        $password = trim($request->get('password'));

        $user->setEmail($email);
        $user->setUsername($userName);
        $user->setPassword($password);

        $errors = $validator->validate($user);

        $response = ['error'=>false];

        if (count($errors)) {
            $response['error'] = true;
            $response['errors'] = [];
            foreach ($errors as $error) {
                $response['errors'][] = $error->getMessage();
            }
            return $this->json($response);
        }

        $entityByEmail = $entityManager->getRepository(User::class)->findOneBy(['email'=>$email]);
        if ($entityByEmail) {
            $response['error'] = true;
            $response['errors'] = ['We already have such email in our database'];
            return $this->json($response);
        }

        $entityByUserName = $entityManager->getRepository(User::class)->findOneBy(['username'=>$userName]);
        if ($entityByUserName) {
            $response['error'] = true;
            $response['errors'] = ['We already have such username in our database'];
            return $this->json($response);
        }

        $hashedPassword = $passwordHasher->hashPassword( $user, $password );
        $user->setPassword($hashedPassword);

        $entityManager->persist($user);
        try{
            $entityManager->flush();
        }catch(\Exception $e) {
            // В реальной аппликации мы бы логировали такие ошибки, а пользователю бы показали "Oops, something went wrong" здесь просто верну ошибку в ответе
            return $this->json(['error'=>true,'errors'=>[$e->getMessage()]]);
        }

        $response['message'] = 'User created';
        $normalizer = new ObjectNormalizer();
        $serializer = new Serializer([$normalizer]);
        $response['data'] = $serializer->normalize($user,null,[AbstractNormalizer::ATTRIBUTES =>['id','username','email']]);
        return $this->json($response);
    }


    #[Route('/api/user/{id}', name: 'api_get_user', methods:['GET'])]
    public function getById(
        int $id,
        UserRepository $userRepository
    ): JsonResponse
    {

        $user = $userRepository->find($id);

        if (!$user) {
            return $this->json([
                'error'     => true,
                'errors'    => ['User not found']
            ]);
        }

        $normalizer = new ObjectNormalizer();
        $serializer = new Serializer([$normalizer]);
        return $this->json([
            'error' => false,
            'data'  => $serializer->normalize($user,null,[AbstractNormalizer::ATTRIBUTES =>['id','username','email']])
        ]);
    }

    #[Route('/api/user/{id}', name: 'api_delete_user', methods:['DELETE'])]
    public function deleteById(
        int $id,
        ManagerRegistry $doctrine
    ): JsonResponse
    {
        $entityManager = $doctrine->getManager();
        $user = $entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            return $this->json([
                'error'     => true,
                'errors'    => ['User not found']
            ]);
        }

        try{
            $entityManager->remove($user);
            $entityManager->flush();
        }catch(\Exception $e) {
            return $this->json([
                'error'     => true,
                'errors'    => [$e->getMessage()]
            ]);
        }

        return $this->json([
            'error' => false,
            'message'  => 'User deleted'
        ]);
    }



    #[Route('/api/user/{id}', name: 'api_update_user', methods:['PATCH'])]
    public function updateById(
        int $id,
        ManagerRegistry $doctrine,
        Request $request,
        ValidatorInterface $validator,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse
    {
        $entityManager = $doctrine->getManager();
        $user = $entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            return $this->json([
                'error'     => true,
                'errors'    => ['User not found']
            ]);
        }

        // Устанавливаем высланные поля (можно менять как все, так и частично)
        if ($username = trim($request->get('username'))) {
            $user->setUsername($username);
        }

        if ($email = trim($request->get('email'))) {
            $user->setEmail($email);
        }

        if ($password = trim($request->get('password'))) {
            $user->setPassword($password);
        }

        if (!$username && !$email && !$password) {
            $response['error'] = true;
            $response['errors'] = ['You must send at least one parameter to update. Maybe you are using form-data instead of x-www-form-url-encoded when processing a PATCH request?'];
            return $this->json($response);
        }

        $errors = $validator->validate($user);

        $response = ['error'=>false];

        if (count($errors)) {
            $response['error'] = true;
            $response['errors'] = [];
            foreach ($errors as $error) {
                $response['errors'][] = $error->getMessage();
            }
            return $this->json($response);
        }

        // Проверяем, не пытаемся ли сохранить данные, уже прописанные у другого пользователя
        $queryBuilder = $doctrine->getManager()->createQueryBuilder('u');
        $query = $queryBuilder->select('u')->from(User::class,'u')
            ->where('u.id != :id')
            ->andWhere(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->eq("u.username",':username'),
                    $queryBuilder->expr()->eq("u.email",':email')
                )
            )->setParameter('id',$user->getId())->setParameter('username',$username)->setParameter('email',$email);

        $existingUsersResult = $query->getQuery()->getResult();

        if ($existingUsersResult) {
            return $this->json([
                'error'     => true,
                'errors'    => ['You are trying to set field values that should be unique and other user already has them']
            ]);
        }

        if ($password) {
            $hashedPassword = $passwordHasher->hashPassword( $user, $password );
            $user->setPassword($hashedPassword);
        }

        $entityManager->persist($user);
        try{
            $entityManager->flush();
        }catch(\Exception $e) {
            return $this->json([
                'error'     => true,
                'errors'    => [$e->getMessage()]
            ]);
        }


        $response['message'] = 'User updated';
        $normalizer = new ObjectNormalizer();
        $serializer = new Serializer([$normalizer]);
        $response['data'] = $serializer->normalize($user,null,[AbstractNormalizer::ATTRIBUTES =>['id','username','email']]);
        return $this->json($response);
    }
}
