CREATE DATABASE IF NOT EXISTS `api-default`;
USE `api-default`;

create table if not exists `users`
(
    id                 char(36)                               not null primary key,
    nome               varchar(100)                           not null,
    sobrenome          varchar(100)                           not null,
    photo_blob         longblob                               null,
    email              varchar(255)                           not null,
    senha              varchar(255)                           null,
    firebase_uid       varchar(255)                           null,
    termos_aceito_em   timestamp  default current_timestamp() not null on update current_timestamp(),
    politica_aceita_em timestamp  default current_timestamp() not null on update current_timestamp(),
    is_active          tinyint(1) default 0                   not null,
    penultimo_acesso   timestamp                              null,
    ultimo_acesso      timestamp                              null,
    criado_em          timestamp  default current_timestamp() not null,
    alterado_em        timestamp  default current_timestamp() not null,
    constraint idx_users_email unique (email),
    constraint idx_users_firebase_uid unique (firebase_uid)
);

create table if not exists `user_password_resets`
(
    user_id           char(36)                              not null primary key,
    reset_code        varchar(255)                          null,
    reset_code_expiry timestamp                             null,
    criado_em         timestamp default current_timestamp() not null on update current_timestamp(),
    constraint fk_user_password_resets_user_id
        foreign key (user_id) references users (id)
            on update cascade on delete cascade
);