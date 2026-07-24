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
    /**
     * Extrae "MM-DD" de la columna DATE, ignorando el año de nacimiento
     * (siempre un placeholder — ver BirthdayService::PLACEHOLDER_YEAR).
     */
    private const string MONTH_DAY_DQL = 'SUBSTRING(b.birthDate, 6, 5)';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Birthday::class);
    }

    /**
     * Cumpleaños habilitados que caen en el mes/día de $date, sin importar el año de nacimiento.
     *
     * @return Birthday[]
     */
    public function findEnabledBirthdaysOn(\DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.enabled = true')
            ->andWhere(self::MONTH_DAY_DQL.' = :monthDay')
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
     * Todos los cumpleaños ordenados por fecha (mes/día), sin importar el año de nacimiento.
     *
     * @return Birthday[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('b')
            ->orderBy(self::MONTH_DAY_DQL, 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Cumpleaños habilitados ordenados por fecha (mes/día), sin importar el año de nacimiento.
     *
     * @return Birthday[]
     */
    public function findEnabledOrdered(): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.enabled = true')
            ->orderBy(self::MONTH_DAY_DQL, 'ASC')
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
