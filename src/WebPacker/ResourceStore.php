<?php
declare(strict_types=1);
use SetBased\Stratum\SqlitePdo\SqlitePdoDataLayer;

/**
 * The data layer.
 */
class ResourceStore extends SqlitePdoDataLayer
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Fixes the depth for JS.
   *
   * @return array[]
   */
  public function resourceFixDepthForJs(): array
  {
    $query = <<< EOT
select src.rsr_id   as  src_rsr_id
,      rsr.rsr_id   as  rsr_rsr_id
,      rsr.rsr_path as  rsr_rsr_path
from   ABC_RESOURCE      src
join   ABC_RESOURCE_TYPE src_rtp  on  src_rtp.rtp_id = src.rtp_id
join   ABC_RESOURCE      rsr      on  1 = 1
join   ABC_RESOURCE_TYPE rsr_rtp  on  rsr_rtp.rtp_id = rsr.rtp_id
where  src_rtp.rtp_name = 'js.main'
and    rsr_rtp.rtp_name = 'js'
and    not exists ( select 1
                    from   ABC_LINK2 cur
                    where  cur.rsr_id_src = src.rsr_id
                    and    cur.rsr_id_src = rsr.rsr_id)
order by rsr.rsr_path
;
EOT;
    $query = str_repeat(PHP_EOL, 5).$query;

    return $this->executeRows($query);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Selects all resources files.
   *
   * @return array[]
   */
  public function resourceGetAll(): array
  {
    $query = <<< EOT
select rsr.rsr_id
,      rsr.rsr_path
,      rsr.rsr_mtime
,      rsr.rsr_depth
,      rsr.rsr_content
,      rsr.rsr_content_optimized
,      rsr.rsr_uri_optimized

,      rtp.rtp_id
,      rtp.rtp_regex
,      rtp.rtp_name
,      rtp.rtp_class
from   ABC_RESOURCE      rsr
join   ABC_RESOURCE_TYPE rtp  on  rtp.rtp_id = rsr.rtp_id
order by rsr.rsr_path
EOT;
    $query = str_repeat(PHP_EOL, 5).$query;

    return $this->executeRows($query);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Selects all resources files at a depth.
   *
   * @param int|null $pRsrDepth The depth.
   *
   * @return array[]
   */
  public function resourceGetAllByDepth(?int $pRsrDepth): array
  {
    $replace = [':p_rsr_depth' => $this->quoteInt($pRsrDepth)];
    $query   = <<< EOT
select rsr.rsr_id
,      rsr.rsr_path
,      rsr.rsr_mtime
,      rsr.rsr_depth
,      rsr.rsr_content
,      rsr.rsr_content_optimized
,      rsr.rsr_uri_optimized

,      rtp.rtp_id
,      rtp.rtp_regex
,      rtp.rtp_name
,      rtp.rtp_class
from   ABC_RESOURCE      rsr
join   ABC_RESOURCE_TYPE rtp  on  rtp.rtp_id = rsr.rtp_id
where  rsr.rsr_depth = :p_rsr_depth
order by rsr.rsr_path
EOT;
    $query = str_repeat(PHP_EOL, 7).$query;

    return $this->executeRows($query, $replace);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Selects all resources by type
   *
   * @param string|null $pRtpName The name of the type.
   *
   * @return array[]
   */
  public function resourceGetAllByType(?string $pRtpName): array
  {
    $replace = [':p_rtp_name' => $this->quoteVarchar($pRtpName)];
    $query   = <<< EOT
select rsr.rsr_id
,      rsr.rsr_path
,      rsr.rsr_content_optimized
from   ABC_RESOURCE      rsr
join   ABC_RESOURCE_TYPE rtp  on  rtp.rtp_id = rsr.rtp_id
where  rtp.rtp_name = :p_rtp_name
order by rsr.rsr_path
EOT;
    $query = str_repeat(PHP_EOL, 7).$query;

    return $this->executeRows($query, $replace);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Selects all optimized resources.
   *
   * @return array[]
   */
  public function resourceGetAllOptimized(): array
  {
    $query = <<< EOT
select rsr_id
,      rsr_path
,      rsr_uri_optimized
from   ABC_RESOURCE
where  rsr_uri_optimized is not null
order by rsr_path
EOT;
    $query = str_repeat(PHP_EOL, 5).$query;

    return $this->executeRows($query);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Selects all optimized and referred resources.
   *
   * @return array[]
   */
  public function resourceGetAllOptimizedReferred(): array
  {
    $query = <<< EOT
select rsr.rsr_id
,      rsr.rsr_path
,      rsr.rsr_mtime
,      rsr.rsr_depth
,      rsr.rsr_content
,      rsr.rsr_content_optimized
,      rsr.rsr_uri_optimized

,      rtp.rtp_id
,      rtp.rtp_regex
,      rtp.rtp_name
,      rtp.rtp_class
from   ABC_RESOURCE      rsr
join   ABC_RESOURCE_TYPE rtp  on  rtp.rtp_id = rsr.rtp_id
where  rsr_uri_optimized is not null
and    rsr_id in ( select rsr_id_rsr
                   from  ABC_LINK2

                   union all

                   select rsr_id
                   from  ABC_LINK1
                 )
order by rsr_uri_optimized
EOT;
    $query = str_repeat(PHP_EOL, 5).$query;

    return $this->executeRows($query);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Selects all resources that are been referred by a resource.
   *
   * @param int|null $pRsrId The ID of the referring resource.
   *
   * @return array[]
   */
  public function resourceGetAllReferredByResource(?int $pRsrId): array
  {
    $replace = [':p_rsr_id' => $this->quoteInt($pRsrId)];
    $query   = <<< EOT
select rsr.rsr_id
,      rsr.rsr_path
,      rsr.rsr_mtime
,      rsr.rsr_depth
,      rsr.rsr_content
,      rsr.rsr_content_optimized
,      rsr.rsr_uri_optimized

,      lk2.lk2_name
,      lk2.lk2_line
,      lk2.lk2_matches
from   ABC_RESOURCE      rsr
join   ABC_LINK2         lk2  on  lk2.rsr_id_rsr = rsr.rsr_id
where  lk2.rsr_id_src = :p_rsr_id
order by lk2.lk2_line
,        lk2.ROWID
EOT;
    $query = str_repeat(PHP_EOL, 7).$query;

    return $this->executeRows($query, $replace);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Selects all resources that are been referred by a source.
   *
   * @param int|null $pSrcId The ID of the referring source.
   *
   * @return array[]
   */
  public function resourceGetAllReferredBySource(?int $pSrcId): array
  {
    $replace = [':p_src_id' => $this->quoteInt($pSrcId)];
    $query   = <<< EOT
select rsr.rsr_id
,      rsr.rsr_path
,      rsr.rsr_mtime
,      rsr.rsr_uri_optimized

,      lk1.lk1_line
,      lk1.lk1_method
,      lk1.lk1_matches
from   ABC_RESOURCE rsr
join   ABC_LINK1    lk1  on  lk1.rsr_id = rsr.rsr_id
where  lk1.src_id = :p_src_id
order by lk1.lk1_line
,        lk1.ROWID
EOT;
    $query = str_repeat(PHP_EOL, 7).$query;

    return $this->executeRows($query, $replace);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Selects all optimized and referred resources.
   *
   * @return array[]
   */
  public function resourceGetAllToBeSaved(): array
  {
    $query = <<< EOT
select rsr_id
,      rsr_path
,      rsr_mtime
,      rsr_content_optimized
,      rsr_uri_optimized

,      rtp_id
,      rtp_regex
,      rtp_name
,      rtp_class
from
(
    select rsr.rsr_id
    ,      rsr.rsr_path
    ,      rsr.rsr_mtime
    ,      rsr.rsr_content_optimized
    ,      rsr.rsr_uri_optimized

    ,      rtp.rtp_id
    ,      rtp.rtp_regex
    ,      rtp.rtp_name
    ,      rtp.rtp_class

    ,      rank() over (partition by rsr.rsr_uri_optimized order by rsr.rsr_mtime desc) rank
    from   ABC_RESOURCE      rsr
    join   ABC_RESOURCE_TYPE rtp  on  rtp.rtp_id = rsr.rtp_id
    where  rsr.rsr_uri_optimized is not null
    and    rsr.rsr_id in ( select rsr_id_rsr
                       from  ABC_LINK2

                       union all

                       select rsr_id
                       from  ABC_LINK1
                     )
) as t
where rank = 1
order by rsr_uri_optimized
EOT;
    $query = str_repeat(PHP_EOL, 5).$query;

    return $this->executeRows($query);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Selects all resources that are not referred to.
   *
   * @return array[]
   */
  public function resourceGetAllUnused(): array
  {
    $query = <<< EOT
select rsr_id
,      rsr_path
,      rsr_mtime
,      rsr_depth
,      rsr_uri_optimized
from   ABC_RESOURCE
where  rsr_depth is null
order by rsr_path
EOT;
    $query = str_repeat(PHP_EOL, 5).$query;

    return $this->executeRows($query);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Selects a resources by its ID.
   *
   * @param int|null $pRsrId The ID of the resource.
   *
   * @return array
   */
  public function resourceGetById(?int $pRsrId): array
  {
    $replace = [':p_rsr_id' => $this->quoteInt($pRsrId)];
    $query   = <<< EOT
select rsr_id
,      rsr_path
,      rsr_mtime
,      rsr_depth
,      rsr_content
,      rsr_content_optimized
,      rsr_uri_optimized
from   ABC_RESOURCE
where  rsr_id = :p_rsr_id
EOT;
    $query = str_repeat(PHP_EOL, 7).$query;

    return $this->executeRow1($query, $replace);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Selects the max depth op the resources.
   *
   * @return int
   */
  public function resourceGetMaxDepth(): int
  {
    $query = <<< EOT
select ifnull(max(rsr_depth), 0)
from   ABC_RESOURCE
EOT;
    $query = str_repeat(PHP_EOL, 6).$query;

    return $this->executeSingleton1($query);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Selects a resource by its full path.
   *
   * @param string|null $pRsrPath The path to the resource.
   *
   * @return array|null
   */
  public function resourceSearchByPath(?string $pRsrPath): ?array
  {
    $replace = [':p_rsr_path' => $this->quoteVarchar($pRsrPath)];
    $query   = <<< EOT
select rsr_id
,      rsr_path
,      rsr_mtime
,      rsr_depth
,      rsr_content
,      rsr_content_optimized
,      rsr_uri_optimized
from   ABC_RESOURCE
where  rsr_path = :p_rsr_path
EOT;
    $query = str_repeat(PHP_EOL, 7).$query;

    return $this->executeRow0($query, $replace);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Selects all resource files.
   *
   * @return array[]
   */
  public function resourceTypeGetAll(): array
  {
    $query = <<< EOT
select rtp_id
,      rtp_regex
,      rtp_class
from   ABC_RESOURCE_TYPE
order by rtp_id
EOT;
    $query = str_repeat(PHP_EOL, 5).$query;

    return $this->executeRows($query);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Updates the depth of resources.
   *
   * @param int|null $pRsrDepth The depth to be set.
   *
   * @return int
   */
  public function resourceUpdateDepth(?int $pRsrDepth): int
  {
    $replace = [':p_rsr_depth' => $this->quoteInt($pRsrDepth)];
    $query   = <<< EOT
update ABC_RESOURCE
set    rsr_depth = :p_rsr_depth
where  rsr_depth is null
and    rsr_id not in ( select rsr_id_rsr
                       from  ABC_LINK2    lk2
                       join  ABC_RESOURCE rsr  on  rsr.rsr_id = lk2.rsr_id_src
                       where rsr.rsr_depth is null
                     )
;

select count(*)
from   ABC_RESOURCE
where  rsr_depth = :p_rsr_depth
;
EOT;
    $query = str_repeat(PHP_EOL, 8).$query;

    return $this->executeSingleton1($query, $replace);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Updates the depth of resources that are not referenced by any other file.
   *
   * @return int
   */
  public function resourceUpdateDepthNotUsed(): int
  {
    $query = <<< EOT
update ABC_RESOURCE
set    rsr_depth = null
where  rsr_id  not in  ( select rsr_id_rsr
                         from  ABC_LINK2    lk2
                         join  ABC_RESOURCE rsr  on  rsr.rsr_id = lk2.rsr_id_src
                         where rsr.rsr_depth is not null

                         union all

                         select rsr_id
                         from  ABC_LINK1  lk1
                         join  ABC_SOURCE src  on  src.src_id = lk1.src_id
                       )
;

select count(*)
from   ABC_RESOURCE
where  rsr_depth is null
;
EOT;
    $query = str_repeat(PHP_EOL, 6).$query;

    return $this->executeSingleton1($query);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Updates the modification time of a resources based on its own mtime and its dependants.
   *
   * @param int|null $pRsrId The ID of the resources.
   */
  public function resourceUpdateMtime(?int $pRsrId): void
  {
    $replace = [':p_rsr_id' => $this->quoteInt($pRsrId)];
    $query   = <<< EOT
update ABC_RESOURCE
set    rsr_mtime =  max(rsr_mtime, ( select ifnull(max(t01.rsr_mtime), 0)
                                     from   ABC_RESOURCE t01
                                     join   ABC_LINK2    t02  on  t02.rsr_id_rsr = t01.rsr_id
                                     where  t02.rsr_id_src = :p_rsr_id))
where  rsr_id = :p_rsr_id
EOT;
    $query = str_repeat(PHP_EOL, 7).$query;

    $this->executeNone($query, $replace);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Updates the name optimized of a resource.
   *
   * @param int|null    $pRsrId           The ID of the resource.
   * @param string|null $pRsrUriOptimized The name of the optimized content.
   */
  public function resourceUpdateNameOptimized(?int $pRsrId, ?string $pRsrUriOptimized): void
  {
    $replace = [':p_rsr_id' => $this->quoteInt($pRsrId), ':p_rsr_uri_optimized' => $this->quoteVarchar($pRsrUriOptimized)];
    $query   = <<< EOT
update ABC_RESOURCE
set    rsr_uri_optimized = :p_rsr_uri_optimized
where  rsr_id = :p_rsr_id
EOT;
    $query = str_repeat(PHP_EOL, 8).$query;

    $this->executeNone($query, $replace);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Updates the optimized content of a resource.
   *
   * @param int|null    $pRsrId               The ID of the resource.
   * @param string|null $pRsrContentOptimized The optimized content.
   */
  public function resourceUpdateOptimized(?int $pRsrId, ?string $pRsrContentOptimized): void
  {
    $replace = [':p_rsr_id' => $this->quoteInt($pRsrId), ':p_rsr_content_optimized' => $this->quoteBlob($pRsrContentOptimized)];
    $query   = <<< EOT
update ABC_RESOURCE
set    rsr_content_optimized = :p_rsr_content_optimized
where  rsr_id = :p_rsr_id
EOT;
    $query = str_repeat(PHP_EOL, 8).$query;

    $this->executeNone($query, $replace);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Selects all source files.
   *
   * @return array[]
   */
  public function sourceGetAll(): array
  {
    $query = <<< EOT
select src.src_id
,      src.src_path
,      src.src_mtime
,      src.src_content

,      stp.stp_id
,      stp.stp_regex
,      stp.stp_name
,      stp.stp_class
from   ABC_SOURCE      src
join   ABC_SOURCE_TYPE stp  on  stp.stp_id = src.stp_id
order by src.src_path
EOT;
    $query = str_repeat(PHP_EOL, 5).$query;

    return $this->executeRows($query);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Selects all source files with references to resources files.
   *
   * @return array[]
   */
  public function sourceGetAllWithReferences(): array
  {
    $query = <<< EOT
select src.src_id
,      src.src_path
,      src.src_mtime
,      src.src_content

,      stp.stp_id
,      stp.stp_regex
,      stp.stp_name
,      stp.stp_class
from   ABC_SOURCE      src
join   ABC_SOURCE_TYPE stp  on  stp.stp_id = src.stp_id
where  src.src_id in ( select src_id
                       from   ABC_LINK1
                 )
order by src.src_path
EOT;
    $query = str_repeat(PHP_EOL, 5).$query;

    return $this->executeRows($query);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Selects all source files.
   *
   * @return array[]
   */
  public function sourceTypeGetAll(): array
  {
    $query = <<< EOT
select stp_id
,      stp_regex
,      stp_class
from   ABC_SOURCE_TYPE
order by stp_id
EOT;
    $query = str_repeat(PHP_EOL, 5).$query;

    return $this->executeRows($query);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Updates the modification time of a sources based on its own mtime and its dependants.
   *
   * @param int|null $pSrcId The ID of the resources.
   *
   * @return array[]
   */
  public function sourceUpdateMtime(?int $pSrcId): array
  {
    $replace = [':p_src_id' => $this->quoteInt($pSrcId)];
    $query   = <<< EOT
update ABC_SOURCE
set    src_mtime =  max(src_mtime, ( select max(t01.rsr_mtime)
                                     from   ABC_RESOURCE t01
                                     join   ABC_LINK1    t02  on  t02.rsr_id = t01.rsr_id
                                     where  t02.src_id = :p_src_id))
where  src_id = :p_src_id
EOT;
    $query = str_repeat(PHP_EOL, 7).$query;

    return $this->executeRows($query, $replace);
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
