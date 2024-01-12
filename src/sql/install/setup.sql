delimiter ; 
create table if not exists `fax_config` (
    `id` varchar(36) NOT NULL DEFAULT '',
    `username` varchar(255) NOT NULL,
    `password` varchar(255) NOT NULL,
    PRIMARY KEY (`id`)
);
