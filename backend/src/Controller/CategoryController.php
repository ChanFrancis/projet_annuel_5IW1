<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Service\JsonPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/categories')]
class CategoryController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categories,
        private readonly JsonPresenter $presenter,
    ) {
    }

    #[Route('', name: 'category_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $items = array_map(
            fn (Category $c) => $this->presenter->category($c),
            $this->categories->findForUser($user),
        );

        return $this->json(['categories' => $items]);
    }

    #[Route('', name: 'category_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = $this->decode($request);
        $name = trim((string) ($data['name'] ?? ''));
        if ('' === $name) {
            return $this->json(['error' => 'Nom requis.'], 422);
        }

        $category = new Category($user, $name);
        if ($parent = $this->resolveOwnedCategory($data['parentId'] ?? null, $user)) {
            $category->setParent($parent);
        }
        $this->em->persist($category);
        $this->em->flush();

        return $this->json($this->presenter->category($category), 201);
    }

    #[Route('/{id}', name: 'category_update', methods: ['PUT', 'PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $category = $this->fetchOwned($id, $user);
        $data = $this->decode($request);

        if (isset($data['name']) && '' !== trim((string) $data['name'])) {
            $category->setName(trim((string) $data['name']));
        }
        if (\array_key_exists('parentId', $data)) {
            $parent = $this->resolveOwnedCategory($data['parentId'], $user);
            // Prevent a category from being its own parent.
            $category->setParent($parent && $parent->getId() != $category->getId() ? $parent : null);
        }
        $this->em->flush();

        return $this->json($this->presenter->category($category));
    }

    #[Route('/{id}', name: 'category_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $category = $this->fetchOwned($id, $user);
        $this->em->remove($category);
        $this->em->flush();

        return $this->json(null, 204);
    }

    private function fetchOwned(string $id, User $user): Category
    {
        $category = $this->resolveOwnedCategory($id, $user);
        if (!$category) {
            throw $this->createNotFoundException('Catégorie introuvable.');
        }

        return $category;
    }

    private function resolveOwnedCategory(mixed $id, User $user): ?Category
    {
        if (!$id || !Uuid::isValid((string) $id)) {
            return null;
        }
        $category = $this->categories->find(Uuid::fromString((string) $id));

        return $category && $category->getOwner()->getId() == $user->getId() ? $category : null;
    }

    /** @return array<string,mixed> */
    private function decode(Request $request): array
    {
        return json_decode($request->getContent() ?: '{}', true) ?? [];
    }
}
