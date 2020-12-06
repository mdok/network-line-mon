
CREATE TABLE t_devices (
    device_id serial PRIMARY KEY,
    hostname varchar(100) NOT NULL,
    device_ip inet NOT NULL,
    sys_description text NOT NULL,
    image_info text NOT NULL,
    image_ver text NOT NULL,
    device_type text NOT NULL,
    feature_set text NOT NULL,
    UNIQUE (device_ip)
);

CREATE TABLE t_lines (
    line_id serial PRIMARY KEY,
    sla_oper_number int NOT NULL,
    line_description varchar(255),
    device_source int NOT NULL,
    device_responder int NOT NULL,
    sla_oper_type int not NULL,
    poll int not NULL,
    UNIQUE (sla_oper_number, device_source),
    FOREIGN KEY (device_source) REFERENCES t_devices(device_id) ON UPDATE CASCADE,
    FOREIGN KEY (device_responder) REFERENCES t_devices(device_id) ON UPDATE CASCADE
);


CREATE TABLE t_sla (
    sla_id serial PRIMARY KEY,
    line_id int NOT NULL,
    rtt_min numeric,
    rtt_avg numeric,
    rtt_max numeric,
    rtt_sum numeric,
    latency_ds_min numeric,
    latency_ds_avg numeric,
    latency_ds_max numeric,
    latency_ds_sum numeric,
    latency_sd_min numeric,
    latency_sd_avg numeric,
    latency_sd_max numeric,
    latency_sd_sum numeric,
    latency_numof_sam int,
    packet_loss int,
    packet_late int,
    packet_outseq_bidi int,
    packet_outof_seq_sd int,
    packet_outof_seq_ds int,
    packet_skipped int,
    jitter_pos_sd_min numeric,
    jitter_pos_sd_max numeric,
    jitter_pos_sd_sum numeric,
    jitter_pos_sd_sam int,
    jitter_neg_sd_min numeric,
    jitter_neg_sd_max numeric,
    jitter_neg_sd_sum numeric,
    jitter_neg_sd_sam int,
    jitter_pos_ds_min numeric,
    jitter_pos_ds_max numeric,
    jitter_pos_ds_sum numeric,
    jitter_pos_ds_sam int,
    jitter_neg_ds_min numeric,
    jitter_neg_ds_max numeric,
    jitter_neg_ds_sum numeric,
    jitter_neg_ds_sam int,
    jitter_avg_sd numeric,
    jitter_avg_ds numeric,
    jitter_avg_bidir numeric,
    jitter_intarr_resp numeric,
    jitter_intarr_sour numeric,
    FOREIGN KEY (line_id) REFERENCES t_lines(line_id) ON UPDATE CASCADE ON DELETE CASCADE


);

CREATE TABLE t_threshold (
    threshold_id serial PRIMARY KEY,
    line_id int NOT NULL,
    sla_type varchar(100) NOT NULL,
    min numeric,
    max numeric,
    exact numeric,
    over_threshold boolean,
    over_min boolean,
    over_max boolean,
    over_exact boolean,
    FOREIGN KEY (line_id) REFERENCES t_lines(line_id) ON UPDATE CASCADE ON DELETE CASCADE
);
CREATE TABLE t_device_stat (
    stat_id serial PRIMARY KEY,
    cpu_last_min numeric,
    device_id int NOT NULL,
    FOREIGN KEY (device_id) REFERENCES t_devices(device_id) ON UPDATE CASCADE on DELETE CASCADE
  
);
CREATE TABLE t_users (
    user_id serial PRIMARY KEY,
    username varchar(100) NOT NULL,
    password varchar NOT NULL,
    role varchar(10) NOT NULL,
    UNIQUE(username)
);
CREATE TABLE t_user_data (
    user_id int NOT NULL,
    active_slas_grid json,
    active_slas_matrix json,
    FOREIGN KEY (user_id) REFERENCES t_users(user_id) ON UPDATE CASCADE on DELETE CASCADE
);

GRANT ALL  ON TABLE t_devices TO nlm;
GRANT ALL  ON TABLE t_lines TO nlm;
GRANT ALL  ON TABLE t_sla TO nlm;
GRANT ALL  ON TABLE t_threshold TO nlm;
GRANT ALL  ON TABLE t_device_stat TO nlm;
GRANT ALL  ON TABLE t_users TO nlm;
GRANT ALL  ON TABLE t_user_data TO nlm;

GRANT ALL  ON SEQUENCE t_devices_device_id_seq TO nlm;
GRANT ALL  ON SEQUENCE t_device_stat_stat_id_seq TO nlm;
GRANT ALL  ON SEQUENCE t_lines_line_id_seq TO nlm;
GRANT ALL  ON SEQUENCE t_sla_sla_id_seq TO nlm;
GRANT ALL  ON SEQUENCE t_threshold_threshold_id_seq TO nlm;
GRANT ALL  ON SEQUENCE t_users_user_id_seq TO nlm;

INSERT INTO t_users (username,password,role) VALUES ('nlm','$2y$12$kZkI2S/Db.ctYXB5mEUkOOdp3wgWuFzaI4h1dFOxKBfraK/I.aiMG','admin');

