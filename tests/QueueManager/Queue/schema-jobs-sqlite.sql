create table main.queue_jobs
(
  job_id            INTEGER
    primary key autoincrement,
  create_time       TEXT default (datetime()) not null,
  queue             TEXT,
  availability_time TEXT,
  attempts          UNSIGNED INT,
  lock_time         TEXT,
  payload           TEXT
);
