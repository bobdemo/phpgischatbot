--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: sentieri; Type: SCHEMA; Schema: -; Owner: developer
--

CREATE SCHEMA sentieri;


ALTER SCHEMA sentieri OWNER TO developer;

--
-- Name: SCHEMA sentieri; Type: COMMENT; Schema: -; Owner: developer
--

COMMENT ON SCHEMA sentieri IS 'raccolta di dati sui sentieri';


--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


--
-- Name: postgis; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS postgis WITH SCHEMA public;


--
-- Name: EXTENSION postgis; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION postgis IS 'PostGIS geometry, geography, and raster spatial types and functions';


SET search_path = public, pg_catalog;

--
-- Name: filetype; Type: TYPE; Schema: public; Owner: developer
--

CREATE TYPE filetype AS ENUM (
    'video',
    'audio',
    'photo',
    'text'
);


ALTER TYPE public.filetype OWNER TO developer;

--
-- Name: season; Type: TYPE; Schema: public; Owner: developer
--

CREATE TYPE season AS ENUM (
    'winter',
    'spring',
    'summer',
    'autumn'
);


ALTER TYPE public.season OWNER TO developer;

--
-- Name: tagtrackpoint; Type: TYPE; Schema: public; Owner: developer
--

CREATE TYPE tagtrackpoint AS ENUM (
    'danger',
    'poi',
    'generic'
);


ALTER TYPE public.tagtrackpoint OWNER TO developer;

--
-- Name: chosen_inline_id_seq; Type: SEQUENCE; Schema: public; Owner: developer
--

CREATE SEQUENCE chosen_inline_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.chosen_inline_id_seq OWNER TO developer;

--
-- Name: conversation_id_seq; Type: SEQUENCE; Schema: public; Owner: developer
--

CREATE SEQUENCE conversation_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.conversation_id_seq OWNER TO developer;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: media_access; Type: TABLE; Schema: public; Owner: developer; Tablespace: 
--

CREATE TABLE media_access (
    date date NOT NULL,
    user_id integer NOT NULL,
    chat_id integer NOT NULL,
    media_id text NOT NULL,
    media_type filetype NOT NULL
);


ALTER TABLE public.media_access OWNER TO developer;

--
-- Name: TABLE media_access; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON TABLE media_access IS 'Tabella che tiene traccia degli accessi ai file multimediali';


--
-- Name: offline_actions; Type: TABLE; Schema: public; Owner: developer; Tablespace: 
--

CREATE TABLE offline_actions (
    trackid integer NOT NULL,
    userid integer NOT NULL,
    chatid integer NOT NULL,
    points integer DEFAULT 0 NOT NULL,
    elements integer DEFAULT 0 NOT NULL,
    date timestamp without time zone
);


ALTER TABLE public.offline_actions OWNER TO developer;

--
-- Name: TABLE offline_actions; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON TABLE offline_actions IS 'Tabella con le operazioni eseguite su di un track di ritorno da offline';


--
-- Name: COLUMN offline_actions.trackid; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN offline_actions.trackid IS 'track a cui si riferiscono le azioni';


--
-- Name: COLUMN offline_actions.userid; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN offline_actions.userid IS 'utente a cui si riferiscono le azioni';


--
-- Name: COLUMN offline_actions.chatid; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN offline_actions.chatid IS 'chat a cui si riferiscono le azioni';


--
-- Name: COLUMN offline_actions.points; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN offline_actions.points IS 'numero trackpoints aggiunti';


--
-- Name: COLUMN offline_actions.elements; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN offline_actions.elements IS 'numero commenti/file aggiunti';


--
-- Name: COLUMN offline_actions.date; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN offline_actions.date IS 'data di inizio della registrazione delle azioni';


--
-- Name: ordering; Type: TABLE; Schema: public; Owner: developer; Tablespace: 
--

CREATE TABLE ordering (
    nextval bigint,
    id integer,
    trackid integer,
    date timestamp without time zone,
    point geometry,
    pathto geometry,
    userid integer,
    chatid integer,
    tag tagtrackpoint
);


ALTER TABLE public.ordering OWNER TO developer;

--
-- Name: trackpoint; Type: TABLE; Schema: public; Owner: developer; Tablespace: 
--

CREATE TABLE trackpoint (
    id integer NOT NULL,
    trackid integer NOT NULL,
    date timestamp without time zone NOT NULL,
    point geometry NOT NULL,
    pathto geometry,
    userid integer,
    chatid integer NOT NULL,
    tag tagtrackpoint DEFAULT 'generic'::tagtrackpoint NOT NULL
);


ALTER TABLE public.trackpoint OWNER TO developer;

--
-- Name: TABLE trackpoint; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON TABLE trackpoint IS 'Tabella con i punti registrati dagli utenti del bot';


--
-- Name: COLUMN trackpoint.id; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN trackpoint.id IS 'Identificativo del trackpoint';


--
-- Name: COLUMN trackpoint.trackid; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN trackpoint.trackid IS 'Identificativo del percorso a cui appartiene il trackpoint ';


--
-- Name: COLUMN trackpoint.date; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN trackpoint.date IS 'timestamp con la data del trackpoint';


--
-- Name: COLUMN trackpoint.point; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN trackpoint.point IS 'Geometria in radianti (lat,lon) con altitudine ';


--
-- Name: COLUMN trackpoint.pathto; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN trackpoint.pathto IS 'la geometria lineare che unisce questo punto al precedente ';


--
-- Name: ordering_trackpoints; Type: VIEW; Schema: public; Owner: developer
--

CREATE VIEW ordering_trackpoints AS
 SELECT count(*) AS rownum,
    foo.id,
    foo.trackid,
    foo.point
   FROM (( SELECT trackpoint.id,
            trackpoint.trackid,
            trackpoint.point
           FROM trackpoint) foo
     JOIN ( SELECT trackpoint.id,
            trackpoint.trackid
           FROM trackpoint) bar ON ((foo.id > bar.id)))
  GROUP BY foo.id, foo.trackid, foo.point
  ORDER BY count(*);


ALTER TABLE public.ordering_trackpoints OWNER TO developer;

--
-- Name: owner; Type: TABLE; Schema: public; Owner: developer; Tablespace: 
--

CREATE TABLE owner (
    userid integer NOT NULL,
    username text,
    firstname text,
    language character varying(2) DEFAULT 'en'::character varying NOT NULL,
    chatid integer DEFAULT 0 NOT NULL,
    lastname text,
    offline boolean,
    last_access timestamp without time zone,
    diff_access interval
);


ALTER TABLE public.owner OWNER TO developer;

--
-- Name: TABLE owner; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON TABLE owner IS 'Tabella con i dati dei creatori dei trackpoint collegati ad una chat ';


--
-- Name: COLUMN owner.userid; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN owner.userid IS 'User identifier in telegram';


--
-- Name: COLUMN owner.username; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN owner.username IS 'User name in telegram';


--
-- Name: COLUMN owner.firstname; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN owner.firstname IS 'User first name in telegram  ';


--
-- Name: COLUMN owner.language; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN owner.language IS 'Linguaggio selezionato dall''utente (codice 639-1)';


--
-- Name: COLUMN owner.chatid; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN owner.chatid IS 'Chat identifier in telegram';


--
-- Name: COLUMN owner.lastname; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN owner.lastname IS 'User last name in telegram';


--
-- Name: COLUMN owner.offline; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN owner.offline IS 'Indica se l''utente non ha risposto all''ultimo quesito';


--
-- Name: COLUMN owner.last_access; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN owner.last_access IS 'data ultimo accesso';


--
-- Name: COLUMN owner.diff_access; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN owner.diff_access IS 'intervallo tra l''ultimo ed il penultimo accesso';


--
-- Name: rating; Type: TABLE; Schema: public; Owner: developer; Tablespace: 
--

CREATE TABLE rating (
    arcid integer NOT NULL,
    sentiero integer,
    samplesum double precision NOT NULL,
    samplecount integer NOT NULL,
    season season NOT NULL,
    year integer NOT NULL
);


ALTER TABLE public.rating OWNER TO developer;

--
-- Name: TABLE rating; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON TABLE rating IS 'Tabella di aggregazione sugli archi dei sentieri  (i valori sono derivati dai msg del bot )';


--
-- Name: COLUMN rating.arcid; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN rating.arcid IS 'identificativo dell''arco nel network sentieri';


--
-- Name: COLUMN rating.sentiero; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN rating.sentiero IS 'identificatore del sentiero nella tabella sentieri';


--
-- Name: roadbook; Type: TABLE; Schema: public; Owner: developer; Tablespace: 
--

CREATE TABLE roadbook (
    id integer NOT NULL,
    text text,
    name text,
    type filetype NOT NULL,
    trackid integer NOT NULL,
    date date NOT NULL
);


ALTER TABLE public.roadbook OWNER TO developer;

--
-- Name: TABLE roadbook; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON TABLE roadbook IS 'Tabella con gli identificativi dei contenuti collegati ai punti sosta.';


--
-- Name: COLUMN roadbook.id; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN roadbook.id IS 'Identificativo della voce del road book';


--
-- Name: COLUMN roadbook.text; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN roadbook.text IS 'Testo inviato rifeito all''elemento';


--
-- Name: COLUMN roadbook.type; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN roadbook.type IS 'Tipo di contenuto ';


--
-- Name: COLUMN roadbook.trackid; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN roadbook.trackid IS 'Id del track di riferimento';


--
-- Name: COLUMN roadbook.date; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN roadbook.date IS 'data di inserimento dell''elemento';


--
-- Name: roadbook_id_seq; Type: SEQUENCE; Schema: public; Owner: developer
--

CREATE SEQUENCE roadbook_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.roadbook_id_seq OWNER TO developer;

--
-- Name: roadbook_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: developer
--

ALTER SEQUENCE roadbook_id_seq OWNED BY roadbook.id;


--
-- Name: track; Type: TABLE; Schema: public; Owner: developer; Tablespace: 
--

CREATE TABLE track (
    id integer NOT NULL,
    chatid integer NOT NULL,
    userid integer NOT NULL,
    start timestamp without time zone DEFAULT now() NOT NULL,
    stop timestamp without time zone,
    name text,
    geom geometry,
    private boolean,
    key double precision DEFAULT date_part('epoch'::text, now()) NOT NULL,
    validated boolean DEFAULT false NOT NULL
);


ALTER TABLE public.track OWNER TO developer;

--
-- Name: TABLE track; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON TABLE track IS 'Tabella con i dati del tracciato';


--
-- Name: COLUMN track.id; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN track.id IS 'Id univoco del tracciato';


--
-- Name: COLUMN track.chatid; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN track.chatid IS 'Chat in cui èstato creato il tracciato';


--
-- Name: COLUMN track.userid; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN track.userid IS 'Utente che ha creato il tracciato';


--
-- Name: COLUMN track.start; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN track.start IS 'Data di apertura del tracciato';


--
-- Name: COLUMN track.stop; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN track.stop IS 'Data di chiusura del tracciato';


--
-- Name: COLUMN track.name; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN track.name IS 'Nome del tracciato';


--
-- Name: COLUMN track.geom; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN track.geom IS 'Campo null, caricato nella validazione o editing ';


--
-- Name: COLUMN track.private; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN track.private IS 'Visibilità tragitto privata o pubblica';


--
-- Name: COLUMN track.key; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN track.key IS 'Chiave di accesso al dato per edit e dati privati';


--
-- Name: COLUMN track.validated; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN track.validated IS 'Tracciato verificato e chiuso  ';


--
-- Name: track_arcs; Type: VIEW; Schema: public; Owner: developer
--

CREATE VIEW track_arcs AS
 SELECT a.id AS "from",
    b.id AS "to",
    a.trackid,
    st_makeline(a.point, b.point) AS st_makeline
   FROM ordering_trackpoints a,
    ordering_trackpoints b
  WHERE ((a.trackid = b.trackid) AND (a.rownum = (b.rownum - 1)));


ALTER TABLE public.track_arcs OWNER TO developer;

--
-- Name: track_id_seq; Type: SEQUENCE; Schema: public; Owner: developer
--

CREATE SEQUENCE track_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.track_id_seq OWNER TO developer;

--
-- Name: track_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: developer
--

ALTER SEQUENCE track_id_seq OWNED BY track.id;


--
-- Name: trackpoint_chatid_seq; Type: SEQUENCE; Schema: public; Owner: developer
--

CREATE SEQUENCE trackpoint_chatid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.trackpoint_chatid_seq OWNER TO developer;

--
-- Name: trackpoint_chatid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: developer
--

ALTER SEQUENCE trackpoint_chatid_seq OWNED BY trackpoint.chatid;


--
-- Name: trackpoint_couple_seq; Type: SEQUENCE; Schema: public; Owner: developer
--

CREATE SEQUENCE trackpoint_couple_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.trackpoint_couple_seq OWNER TO developer;

--
-- Name: trackpoint_id_seq; Type: SEQUENCE; Schema: public; Owner: developer
--

CREATE SEQUENCE trackpoint_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.trackpoint_id_seq OWNER TO developer;

--
-- Name: trackpoint_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: developer
--

ALTER SEQUENCE trackpoint_id_seq OWNED BY trackpoint.id;


--
-- Name: trackpoint_view; Type: VIEW; Schema: public; Owner: developer
--

CREATE VIEW trackpoint_view AS
 SELECT trackpoint.id,
    trackpoint.date,
    trackpoint.trackid,
    trackpoint.point AS geom3003
   FROM trackpoint;


ALTER TABLE public.trackpoint_view OWNER TO developer;

SET search_path = sentieri, pg_catalog;

--
-- Name: points; Type: TABLE; Schema: sentieri; Owner: developer; Tablespace: 
--

CREATE TABLE points (
    gid integer,
    sentiero numeric(10,0),
    elev numeric,
    geom public.geometry(Point,3857)
);


ALTER TABLE sentieri.points OWNER TO developer;

SET search_path = public, pg_catalog;

--
-- Name: id; Type: DEFAULT; Schema: public; Owner: developer
--

ALTER TABLE ONLY roadbook ALTER COLUMN id SET DEFAULT nextval('roadbook_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: developer
--

ALTER TABLE ONLY track ALTER COLUMN id SET DEFAULT nextval('track_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: developer
--

ALTER TABLE ONLY trackpoint ALTER COLUMN id SET DEFAULT nextval('trackpoint_id_seq'::regclass);


--
-- Name: offline_pkey; Type: CONSTRAINT; Schema: public; Owner: developer; Tablespace: 
--

ALTER TABLE ONLY offline_actions
    ADD CONSTRAINT offline_pkey PRIMARY KEY (trackid, userid, chatid);


--
-- Name: rating_pkey; Type: CONSTRAINT; Schema: public; Owner: developer; Tablespace: 
--

ALTER TABLE ONLY rating
    ADD CONSTRAINT rating_pkey PRIMARY KEY (arcid, season, year);


--
-- Name: resource_access_pkey; Type: CONSTRAINT; Schema: public; Owner: developer; Tablespace: 
--

ALTER TABLE ONLY media_access
    ADD CONSTRAINT resource_access_pkey PRIMARY KEY (user_id, chat_id);


--
-- Name: roadbook_pkey; Type: CONSTRAINT; Schema: public; Owner: developer; Tablespace: 
--

ALTER TABLE ONLY roadbook
    ADD CONSTRAINT roadbook_pkey PRIMARY KEY (id);


--
-- Name: track_pkey; Type: CONSTRAINT; Schema: public; Owner: developer; Tablespace: 
--

ALTER TABLE ONLY track
    ADD CONSTRAINT track_pkey PRIMARY KEY (id);


--
-- Name: trackpoint_pkey; Type: CONSTRAINT; Schema: public; Owner: developer; Tablespace: 
--

ALTER TABLE ONLY trackpoint
    ADD CONSTRAINT trackpoint_pkey PRIMARY KEY (id);


--
-- Name: user_pk; Type: CONSTRAINT; Schema: public; Owner: developer; Tablespace: 
--

ALTER TABLE ONLY owner
    ADD CONSTRAINT user_pk PRIMARY KEY (userid, chatid);


--
-- Name: roadbook_track_fk; Type: FK CONSTRAINT; Schema: public; Owner: developer
--

ALTER TABLE ONLY roadbook
    ADD CONSTRAINT roadbook_track_fk FOREIGN KEY (trackid) REFERENCES track(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: track_fk; Type: FK CONSTRAINT; Schema: public; Owner: developer
--

ALTER TABLE ONLY trackpoint
    ADD CONSTRAINT track_fk FOREIGN KEY (trackid) REFERENCES track(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: track_fkey; Type: FK CONSTRAINT; Schema: public; Owner: developer
--

ALTER TABLE ONLY track
    ADD CONSTRAINT track_fkey FOREIGN KEY (chatid, userid) REFERENCES owner(chatid, userid) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: public; Type: ACL; Schema: -; Owner: postgres
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--