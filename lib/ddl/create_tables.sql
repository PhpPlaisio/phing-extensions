pragma foreign_keys = on;

create table ABC_SOURCE_TYPE
(
  stp_id    integer not null primary key asc,
  stp_regex varchar not null,
  stp_name  varchar not null,
  stp_class varchar not null
);

create table ABC_SOURCE
(
  src_id      integer not null primary key asc,
  stp_id      integer not null,                 -- The type of the source file.
  src_path    varchar not null,                 -- The full path to the source file.
  src_mtime   integer not null,                 -- The modification time of the file.
  src_content blob not null,                    -- The content of the source file.
  foreign key(stp_id) references ABC_SOURCE_TYPE(stp_id)
);

create unique index idx_abc_source_01 on ABC_SOURCE(src_path);
create index idx_abc_source_0 on ABC_SOURCE(stp_id);

create table ABC_RESOURCE_TYPE
(
  rtp_id    integer not null primary key asc,
  rtp_regex varchar not null,
  rtp_name  varchar not null,
  rtp_class varchar not null
);

create table ABC_RESOURCE
(
  rsr_id                integer not null primary key asc,
  rtp_id                integer not null,                 -- The type of the resource file.
  rsr_path              varchar,                          -- The full path to the source file. If null the resource
                                                          -- has been generated from other resources.
  rsr_mtime             integer not null,                 -- The modification time of the file.
  rsr_depth             integer default null,             -- The depth of the resource (starting from 1)
  rsr_content           blob,                             -- The content of the resource file.
  rsr_content_optimized blob default null,                -- The optimized content of the source.
  rsr_uri_optimized     varchar,                          -- The URI of the resource with the optimized content.
  foreign key(rtp_id) references ABC_RESOURCE_TYPE(rtp_id)
);

create unique index idx_abc_resource_01 on ABC_RESOURCE(rsr_path);
create index idx_abc_resource_02 on ABC_RESOURCE(rtp_id);

-- Sources referring to resources.
create table ABC_LINK1
(
  src_id      integer not null,
  rsr_id      integer not null,
  lk1_line    integer not null,
  lk1_method  varchar not null,
  lk1_matches varchar not null,                            -- The serialized matches as returned by preg_match().
  foreign key(src_id) references ABC_SOURCE(src_id),
  foreign key(rsr_id) references ABC_RESOURCE(rsr_id)
);

create unique index idx_abc_link1_01 on ABC_LINK1(src_id, rsr_id, lk1_line);
create index idx_abc_link1_02 on ABC_LINK1(rsr_id);

-- Resources referring to resources.
create table ABC_LINK2
(
  rsr_id_src  integer not null,
  rsr_id_rsr  integer not null,
  lk2_name    varchar not null,                              -- The name in the source (rsr_id_src) that is used to
                                                             -- refer to the other resource (rsr_id_rsr).
  lk2_line    integer not null,
  lk2_matches varchar,                                       -- The serialized matches as returned by preg_match().
  foreign key(rsr_id_src) references ABC_RESOURCE(rsr_id),
  foreign key(rsr_id_rsr) references ABC_RESOURCE(rsr_id)
);

create unique index idx_abc_link2_01 on ABC_LINK2(rsr_id_src, rsr_id_rsr, lk2_line);
create index idx_abc_link2_02 on ABC_LINK2(rsr_id_rsr);
