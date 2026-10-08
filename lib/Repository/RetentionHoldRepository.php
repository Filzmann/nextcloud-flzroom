<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Repository;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** App-local hold source; the privacy runtime never reads this table directly. */
final class RetentionHoldRepository {
    public function __construct(private IDBConnection $db) {}

    public function activeFor(string $policyId, string $recordReference): ?array {
        $qb=$this->db->getQueryBuilder();
        $row=$qb->select('*')->from('flz_room_retention_holds')
            ->where($qb->expr()->eq('policy_id',$qb->createNamedParameter($policyId,IQueryBuilder::PARAM_STR)))
            ->andWhere($qb->expr()->eq('record_ref',$qb->createNamedParameter($recordReference,IQueryBuilder::PARAM_STR)))
            ->andWhere($qb->expr()->isNull('released_at'))
            ->orderBy('placed_at','DESC')->setMaxResults(1)->executeQuery()->fetchAssociative();
        return $row===false?null:$this->map($row);
    }

    public function place(string $policyId,string $recordReference,string $reasonCode,string $evidenceReference,string $placedBy,DateTimeImmutable $placedAt,DateTimeImmutable $reviewDueAt):int {
        $qb=$this->db->getQueryBuilder();
        $qb->insert('flz_room_retention_holds')
            ->setValue('policy_id',$qb->createNamedParameter($policyId,IQueryBuilder::PARAM_STR))
            ->setValue('record_ref',$qb->createNamedParameter($recordReference,IQueryBuilder::PARAM_STR))
            ->setValue('reason_code',$qb->createNamedParameter($reasonCode,IQueryBuilder::PARAM_STR))
            ->setValue('evidence_ref',$qb->createNamedParameter($evidenceReference,IQueryBuilder::PARAM_STR))
            ->setValue('placed_by',$qb->createNamedParameter($placedBy,IQueryBuilder::PARAM_STR))
            ->setValue('placed_at',$qb->createNamedParameter($placedAt,IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
            ->setValue('review_due_at',$qb->createNamedParameter($reviewDueAt,IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
            ->setValue('released_by',$qb->createNamedParameter(null,IQueryBuilder::PARAM_NULL))
            ->setValue('released_at',$qb->createNamedParameter(null,IQueryBuilder::PARAM_NULL))
            ->executeStatement();
        return $qb->getLastInsertId();
    }

    public function release(int $id,string $releasedBy,DateTimeImmutable $releasedAt):bool {
        $qb=$this->db->getQueryBuilder();
        return $qb->update('flz_room_retention_holds')
            ->set('released_by',$qb->createNamedParameter($releasedBy,IQueryBuilder::PARAM_STR))
            ->set('released_at',$qb->createNamedParameter($releasedAt,IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
            ->where($qb->expr()->eq('id',$qb->createNamedParameter($id,IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->isNull('released_at'))->executeStatement()>0;
    }

    public function releaseActive(string $policyId,string $recordReference,string $releasedBy,DateTimeImmutable $releasedAt):bool {
        $qb=$this->db->getQueryBuilder();
        return $qb->update('flz_room_retention_holds')
            ->set('released_by',$qb->createNamedParameter($releasedBy,IQueryBuilder::PARAM_STR))
            ->set('released_at',$qb->createNamedParameter($releasedAt,IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
            ->where($qb->expr()->eq('policy_id',$qb->createNamedParameter($policyId,IQueryBuilder::PARAM_STR)))
            ->andWhere($qb->expr()->eq('record_ref',$qb->createNamedParameter($recordReference,IQueryBuilder::PARAM_STR)))
            ->andWhere($qb->expr()->isNull('released_at'))->executeStatement()>0;
    }

    public function releasedBefore(DateTimeImmutable $cutoff,int $limit):array {
        $qb=$this->db->getQueryBuilder();
        $rows=$qb->select('*')->from('flz_room_retention_holds')
            ->where($qb->expr()->lte('released_at',$qb->createNamedParameter($cutoff,IQueryBuilder::PARAM_DATETIME_IMMUTABLE)))
            ->orderBy('released_at','ASC')->addOrderBy('id','ASC')->setMaxResults($limit)->executeQuery()->fetchAllAssociative();
        return array_map([$this,'map'],$rows);
    }

    public function deleteReleased(int $id,DateTimeImmutable $releasedAt):bool {
        $qb=$this->db->getQueryBuilder();
        return $qb->delete('flz_room_retention_holds')
            ->where($qb->expr()->eq('id',$qb->createNamedParameter($id,IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('released_at',$qb->createNamedParameter($releasedAt,IQueryBuilder::PARAM_DATETIME_IMMUTABLE)))
            ->executeStatement()>0;
    }

    private function map(array $row):array{return['id'=>(int)$row['id'],'policyId'=>(string)$row['policy_id'],'recordReference'=>(string)$row['record_ref'],'reasonCode'=>(string)$row['reason_code'],'evidenceReference'=>(string)$row['evidence_ref'],'placedBy'=>(string)$row['placed_by'],'placedAt'=>$this->date($row['placed_at']),'reviewDueAt'=>$this->date($row['review_due_at']),'releasedBy'=>$row['released_by']===null?null:(string)$row['released_by'],'releasedAt'=>$row['released_at']===null?null:$this->date($row['released_at'])];}
    private function date(mixed $value):DateTimeImmutable{return$value instanceof DateTimeInterface?DateTimeImmutable::createFromInterface($value):new DateTimeImmutable((string)$value,new DateTimeZone('UTC'));}
}
