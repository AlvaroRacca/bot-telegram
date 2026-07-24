<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Birthday;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Birthday>
 */
class BirthdayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Birthday::class);
    }

    /**
     * Cumpleaños habilitados que caen en el mes/día de $date, sin importar el año
     * de nacimiento. Compara solo "MM-DD" via SUBSTRING (función DQL estándar,
     * sin dependencias extra), así el mismo query sirve todos los años.
     *
     * @return Birthday[]
     */
    public function findEnabledBirthdaysOn(\DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.enabled = true')
            ->andWhere('SUBSTRING(b.birthDate, 6, 5) = :monthDay')
            ->setParameter('monthDay', $date->format('m-d'))
            ->orderBy('b.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByName(string $name): ?Birthday
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Birthday[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(Birthday $birthday): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($birthday);
        $entityManager->flush();
    }

    public function delete(Birthday $birthday): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->remove($birthday);
        $entityManager->flush();
    }
}
