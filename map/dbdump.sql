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
-- Name: pathway; Type: SCHEMA; Schema: -; Owner: developer
--

CREATE SCHEMA pathway;


ALTER SCHEMA pathway OWNER TO developer;

--
-- Name: SCHEMA pathway; Type: COMMENT; Schema: -; Owner: developer
--

COMMENT ON SCHEMA pathway IS 'raccolta di dati sui sentieri';


--
-- Name: zone1; Type: SCHEMA; Schema: -; Owner: developer
--

CREATE SCHEMA zone1;


ALTER SCHEMA zone1 OWNER TO developer;

--
-- Name: SCHEMA zone1; Type: COMMENT; Schema: -; Owner: developer
--

COMMENT ON SCHEMA zone1 IS 'Dati per la zona con id 1 : sentieri, archi e nodi del network';


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


--
-- Name: pgrouting; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS pgrouting WITH SCHEMA zone1;


--
-- Name: EXTENSION pgrouting; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION pgrouting IS 'pgRouting Extension';


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

SET search_path = pathway, pg_catalog;

--
-- Name: googleencodepolygon(public.geometry); Type: FUNCTION; Schema: pathway; Owner: developer
--

CREATE FUNCTION googleencodepolygon(g1 public.geometry) RETURNS text
    LANGUAGE plpgsql
    AS $$
DECLARE
 ng INT;        -- Store number of Geometries in the Polygon.
 g INT;         -- Counter for the current geometry number during outer loop.
 g2 GEOMETRY;   -- Current geometry feature isolated by the outer loop.
 nr INT;        -- Store number of internal ring parts in the Polygon.
 r INT;         -- Counter for the current inner-ring part.
 r1 GEOMETRY;   -- Exterior ring part isolated BEFORE the inner loop.
 r2 GEOMETRY;   -- Inner-ring part isolated within the inner loop.
 gEncoded TEXT; -- Completed Google Encoding.
BEGIN
 gEncoded = '';
 ng = ST_NumGeometries(g1);
 g = 1;
 FOR g IN 1..ng BY 1 LOOP
     g2 = ST_GeometryN(g1, g);
     if g > 1 then gEncoded = gEncoded || chr(8224); END IF;
     -- Get ExteriorRing now; if there are any holes, get them later in the loop..
     r1 = ST_ExteriorRing(g2);
     gEncoded = gEncoded || GoogleEncodeLine(r1);
     nr = ST_NRings(g2);
     if nr > 1 then
       -- One (1) is because interior rings is one-based.
       -- And nr-1 is because ring count includes the boundary.
       FOR r IN 1..(nr-1) BY 1 LOOP
         r2 = ST_InteriorRingN(g2, r);
         gEncoded = gEncoded || chr(8225) || GoogleEncodeLine(r2);
       END LOOP;
     END IF;
 END LOOP;
 RETURN gEncoded;
End
$$;


ALTER FUNCTION pathway.googleencodepolygon(g1 public.geometry) OWNER TO developer;

SET search_path = public, pg_catalog;

--
-- Name: _gcb_create_matrix(text); Type: FUNCTION; Schema: public; Owner: developer
--

CREATE FUNCTION _gcb_create_matrix(zone text) RETURNS character varying
    LANGUAGE plpgsql
    AS $$DECLARE
    c record;
    r record;
    sql text;
    sqlarcs text;
    i integer;
BEGIN
    i := 0;
    for c in execute 'select "start", "end" from zone'||zone||'.distance_matrix ' loop
        sqlarcs  := 'select "id", "from"::integer as source, "to"::integer as target, '
                || ' "length"::float as cost, x1, y1, x2, y2 from zone'||zone||'.arcs ';
        sql := ' select st_linemerge(st_collect(b.geom)) as geom , string_agg(a.id2::text,''_'') as arcs, sum(a.cost) as cost from '
            || '( select * from pgr_astar ('||quote_literal(sqlarcs)||', '|| c."start" || ', ' || c."end" 
            || ', false, false ) ) a, zone'||zone||'.arcs b where b.id = a.id2 ';
        for r in execute sql loop
            execute 'update zone'||zone||'.distance_matrix set arcs = '|| quote_literal(r.arcs::text) 
                     || ' where "start" = '||c."start"||' and "end" = '||c."end";
        i := i + 1;
        end loop;
    end loop;
    return i || 'rows added to distance matrix in the schema "zone' || zone || '"';
 END$$;


ALTER FUNCTION public._gcb_create_matrix(zone text) OWNER TO developer;

--
-- Name: googleencodeline(geometry); Type: FUNCTION; Schema: public; Owner: developer
--

CREATE FUNCTION googleencodeline(g geometry) RETURNS text
    LANGUAGE plpgsql
    AS $$DECLARE
  pt1 GEOMETRY;
  pt2 GEOMETRY;
  p INT; np INT;
  deltaX INT;
  deltaY INT;
  enX VARCHAR(255);
  enY VARCHAR(255);
  gEncoded TEXT;
BEGIN
  IF g IS NULL THEN RETURN NULL; END IF;
  gEncoded = '';
  np = ST_NPoints(g);

  IF np > 3 THEN
    g = ST_SimplifyPreserveTopology(g, 0.00001);
    np = ST_NPoints(g);
  END IF;

  pt1 = ST_SetSRID(ST_MakePoint(0, 0),4326);

  FOR p IN 1..np BY 1 LOOP
    pt2 = ST_PointN(g, p);
    deltaX = (floor(ST_X(pt2)*1e5)-floor(ST_X(pt1)*1e5))::INT;
    deltaY = (floor(ST_Y(pt2)*1e5)-floor(ST_Y(pt1)*1e5))::INT;
    enX = GoogleEncodeSignedInteger(deltaX);
    enY = GoogleEncodeSignedInteger(deltaY);
    gEncoded = gEncoded || enY || enX;

    pt1 = ST_SetSRID(ST_MakePoint(ST_X(pt2), ST_Y(pt2)),4326);
  END LOOP;
RETURN gEncoded;
End
$$;


ALTER FUNCTION public.googleencodeline(g geometry) OWNER TO developer;

--
-- Name: googleencodepolygon(geometry); Type: FUNCTION; Schema: public; Owner: developer
--

CREATE FUNCTION googleencodepolygon(g1 geometry) RETURNS text
    LANGUAGE plpgsql
    AS $$
DECLARE
 ng INT;        -- Store number of Geometries in the Polygon.
 g INT;         -- Counter for the current geometry number during outer loop.
 g2 GEOMETRY;   -- Current geometry feature isolated by the outer loop.
 nr INT;        -- Store number of internal ring parts in the Polygon.
 r INT;         -- Counter for the current inner-ring part.
 r1 GEOMETRY;   -- Exterior ring part isolated BEFORE the inner loop.
 r2 GEOMETRY;   -- Inner-ring part isolated within the inner loop.
 gEncoded TEXT; -- Completed Google Encoding.
BEGIN
 gEncoded = '';
 ng = ST_NumGeometries(g1);
 g = 1;
 FOR g IN 1..ng BY 1 LOOP
     g2 = ST_GeometryN(g1, g);
     if g > 1 then gEncoded = gEncoded || chr(8224); END IF;
     -- Get ExteriorRing now; if there are any holes, get them later in the loop..
     r1 = ST_ExteriorRing(g2);
     gEncoded = gEncoded || GoogleEncodeLine(r1);
     nr = ST_NRings(g2);
     if nr > 1 then
       -- One (1) is because interior rings is one-based.
       -- And nr-1 is because ring count includes the boundary.
       FOR r IN 1..(nr-1) BY 1 LOOP
         r2 = ST_InteriorRingN(g2, r);
         gEncoded = gEncoded || chr(8225) || GoogleEncodeLine(r2);
       END LOOP;
     END IF;
 END LOOP;
 RETURN gEncoded;
End
$$;


ALTER FUNCTION public.googleencodepolygon(g1 geometry) OWNER TO developer;

--
-- Name: googleencodesignedinteger(integer); Type: FUNCTION; Schema: public; Owner: developer
--

CREATE FUNCTION googleencodesignedinteger(c integer) RETURNS character varying
    LANGUAGE plpgsql
    AS $$
DECLARE
  e VARCHAR(255);
  s BIT(32);
  b BIT(6);
  n INT;
BEGIN
 e = '';
 s = (c::BIT(32))<<1;

 IF s::INT < 0 THEN
   s = ~s;
   END IF;

 WHILE s::INT >= B'100000'::INT LOOP
   b = B'100000' | (('0'||substring(s, 28, 5))::BIT(6));
   n = b::INT + 63;
   e = e || chr(n);
   s = s >> 5;
 END LOOP;
 e = e || chr(s::INT+63);

RETURN e;
End
$$;


ALTER FUNCTION public.googleencodesignedinteger(c integer) OWNER TO developer;

SET search_path = pathway, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: diagram; Type: TABLE; Schema: pathway; Owner: developer; Tablespace: 
--

CREATE TABLE diagram (
    "order" integer NOT NULL,
    pathway integer NOT NULL,
    distance numeric,
    elev numeric
);


ALTER TABLE pathway.diagram OWNER TO developer;

--
-- Name: TABLE diagram; Type: COMMENT; Schema: pathway; Owner: developer
--

COMMENT ON TABLE diagram IS 'Valori di altitudine dei punti ordinati per sentieri e dallo start verso l''end dello stesso';


--
-- Name: COLUMN diagram.distance; Type: COMMENT; Schema: pathway; Owner: developer
--

COMMENT ON COLUMN diagram.distance IS 'distanza dall''inizio del path';


--
-- Name: pathway; Type: TABLE; Schema: pathway; Owner: developer; Tablespace: 
--

CREATE TABLE pathway (
    id integer NOT NULL,
    name text,
    type text,
    geom public.geometry,
    code integer,
    start public.geometry,
    finish public.geometry,
    zone integer DEFAULT 1 NOT NULL,
    webencode text,
    elevation character varying DEFAULT '[]'::character varying
);


ALTER TABLE pathway.pathway OWNER TO developer;

--
-- Name: TABLE pathway; Type: COMMENT; Schema: pathway; Owner: developer
--

COMMENT ON TABLE pathway IS 'Tabella con i sentieri';


--
-- Name: COLUMN pathway.zone; Type: COMMENT; Schema: pathway; Owner: developer
--

COMMENT ON COLUMN pathway.zone IS 'L''area attrezzata che contiene il sentiero ';


--
-- Name: pathway_v; Type: VIEW; Schema: pathway; Owner: developer
--

CREATE VIEW pathway_v AS
 SELECT pathway.id,
    pathway.code,
    pathway.name,
    pathway.zone,
    pathway.type,
    pathway.geom,
    pathway.webencode
   FROM pathway;


ALTER TABLE pathway.pathway_v OWNER TO developer;

--
-- Name: sentieri_id_seq; Type: SEQUENCE; Schema: pathway; Owner: developer
--

CREATE SEQUENCE sentieri_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE pathway.sentieri_id_seq OWNER TO developer;

--
-- Name: sentieri_id_seq; Type: SEQUENCE OWNED BY; Schema: pathway; Owner: developer
--

ALTER SEQUENCE sentieri_id_seq OWNED BY pathway.id;


--
-- Name: zone; Type: TABLE; Schema: pathway; Owner: developer; Tablespace: 
--

CREATE TABLE zone (
    id integer NOT NULL,
    name text NOT NULL,
    geom public.geometry NOT NULL,
    descr text,
    webencode text
);


ALTER TABLE pathway.zone OWNER TO developer;

--
-- Name: TABLE zone; Type: COMMENT; Schema: pathway; Owner: developer
--

COMMENT ON TABLE zone IS 'Tabella con le aree ricettive per il turismo attivo';


--
-- Name: COLUMN zone.descr; Type: COMMENT; Schema: pathway; Owner: developer
--

COMMENT ON COLUMN zone.descr IS 'Descrizione dell''area per le attività di trekking';


--
-- Name: zone_id_seq; Type: SEQUENCE; Schema: pathway; Owner: developer
--

CREATE SEQUENCE zone_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE pathway.zone_id_seq OWNER TO developer;

--
-- Name: zone_id_seq; Type: SEQUENCE OWNED BY; Schema: pathway; Owner: developer
--

ALTER SEQUENCE zone_id_seq OWNED BY zone.id;


--
-- Name: zone_name_seq; Type: SEQUENCE; Schema: pathway; Owner: developer
--

CREATE SEQUENCE zone_name_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE pathway.zone_name_seq OWNER TO developer;

--
-- Name: zone_name_seq; Type: SEQUENCE OWNED BY; Schema: pathway; Owner: developer
--

ALTER SEQUENCE zone_name_seq OWNED BY zone.name;


--
-- Name: zone_v; Type: VIEW; Schema: pathway; Owner: developer
--

CREATE VIEW zone_v AS
 SELECT zone.id,
    zone.name,
    zone.geom,
    zone.descr,
    a.ids
   FROM (zone
     LEFT JOIN ( SELECT pathway.zone,
            string_agg((pathway.id)::text, '_'::text) AS ids
           FROM pathway
          GROUP BY pathway.zone) a ON ((zone.id = a.zone)));


ALTER TABLE pathway.zone_v OWNER TO developer;

SET search_path = public, pg_catalog;

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
-- Name: track; Type: TABLE; Schema: public; Owner: developer; Tablespace: 
--

CREATE TABLE track (
    id integer NOT NULL,
    chatid integer NOT NULL,
    userid integer NOT NULL,
    start timestamp without time zone DEFAULT now() NOT NULL,
    stop timestamp without time zone,
    name text,
    private boolean,
    visibility_key double precision,
    validated boolean DEFAULT false NOT NULL,
    management_key double precision
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
-- Name: COLUMN track.private; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN track.private IS 'Visibilità tragitto privata o pubblica';


--
-- Name: COLUMN track.visibility_key; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN track.visibility_key IS 'Chiave di accesso al dato per visualizzazione';


--
-- Name: COLUMN track.validated; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN track.validated IS 'Tracciato verificato e chiuso  ';


--
-- Name: COLUMN track.management_key; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN track.management_key IS 'Chiave di modifica del dato';


--
-- Name: trackpoint; Type: TABLE; Schema: public; Owner: developer; Tablespace: 
--

CREATE TABLE trackpoint (
    id integer NOT NULL,
    trackid integer NOT NULL,
    date timestamp without time zone NOT NULL,
    point geometry NOT NULL,
    path geometry,
    userid integer NOT NULL,
    chatid integer NOT NULL,
    tag tagtrackpoint DEFAULT 'generic'::tagtrackpoint NOT NULL,
    webencoded text,
    zone integer
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
-- Name: COLUMN trackpoint.path; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN trackpoint.path IS 'la geometria lineare che unisce questo punto al precedente ';


--
-- Name: COLUMN trackpoint.userid; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN trackpoint.userid IS 'Identificatore dell''utente che ha creato il trackpoint';


--
-- Name: COLUMN trackpoint.webencoded; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN trackpoint.webencoded IS 'la linea codificata per il web';


--
-- Name: ordering_trackpoints; Type: VIEW; Schema: public; Owner: developer
--

CREATE VIEW ordering_trackpoints AS
 SELECT count(*) AS rownum,
    foo.id,
    foo.trackid,
    foo.point,
    foo.userid
   FROM (( SELECT trackpoint.id,
            trackpoint.trackid,
            trackpoint.point,
            trackpoint.userid,
            track.validated
           FROM trackpoint,
            track
          WHERE (((track.id = trackpoint.trackid) AND track.validated) AND (track.private = false))) foo
     JOIN ( SELECT trackpoint.id,
            trackpoint.trackid,
            track.validated
           FROM trackpoint,
            track
          WHERE (((track.id = trackpoint.trackid) AND track.validated) AND (track.private = false))) bar ON ((foo.id > bar.id)))
  GROUP BY foo.id, foo.trackid, foo.point, foo.userid
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
    diff_access interval,
    expired boolean DEFAULT false NOT NULL,
    session text
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
-- Name: COLUMN owner.expired; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN owner.expired IS 'Indica che l''utente non è più nella chat';


--
-- Name: COLUMN owner.session; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN owner.session IS 'Stringa di sessione delle azioni fatte offline';


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
    date timestamp without time zone NOT NULL,
    userid integer NOT NULL,
    anchor geometry
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
-- Name: COLUMN roadbook.userid; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN roadbook.userid IS 'Utente che ha registrato il contenuto';


--
-- Name: COLUMN roadbook.anchor; Type: COMMENT; Schema: public; Owner: developer
--

COMMENT ON COLUMN roadbook.anchor IS 'ancora su mappa aggiunta dall''utente';


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
-- Name: track_arc; Type: VIEW; Schema: public; Owner: developer
--

CREATE VIEW track_arc AS
 SELECT a.id AS "from",
    b.id AS "to",
    a.trackid,
    st_makeline(a.point, b.point) AS line,
    ARRAY[st_x(a.point), st_y(a.point), st_x(b.point), st_y(b.point)] AS coordinates,
    a.userid
   FROM ordering_trackpoints a,
    ordering_trackpoints b
  WHERE ((a.trackid = b.trackid) AND (a.rownum = (b.rownum - 1)));


ALTER TABLE public.track_arc OWNER TO developer;

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
-- Name: track_point; Type: VIEW; Schema: public; Owner: developer
--

CREATE VIEW track_point AS
 SELECT trackpoint.id,
    trackpoint.date,
    trackpoint.trackid,
    trackpoint.point AS geom,
    ARRAY[st_x(trackpoint.point), st_y(trackpoint.point)] AS coordinates,
    trackpoint.userid
   FROM trackpoint,
    track
  WHERE (((track.private = false) AND track.validated) AND (trackpoint.trackid = track.id))
  ORDER BY trackpoint.date;


ALTER TABLE public.track_point OWNER TO developer;

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


SET search_path = zone1, pg_catalog;

--
-- Name: arcs; Type: TABLE; Schema: zone1; Owner: developer; Tablespace: 
--

CREATE TABLE arcs (
    id integer,
    "from" integer,
    "to" integer,
    sentieri text,
    geom public.geometry,
    length integer,
    x1 double precision,
    y1 double precision,
    z1 double precision,
    x2 double precision,
    y2 double precision,
    z2 double precision
);


ALTER TABLE zone1.arcs OWNER TO developer;

--
-- Name: arcs_def; Type: TABLE; Schema: zone1; Owner: developer; Tablespace: 
--

CREATE TABLE arcs_def (
    id integer,
    "from" integer,
    "to" integer,
    sentiero integer,
    sentieri text
);


ALTER TABLE zone1.arcs_def OWNER TO developer;

--
-- Name: distance_matrix; Type: TABLE; Schema: zone1; Owner: developer; Tablespace: 
--

CREATE TABLE distance_matrix (
    start numeric(10,0),
    "end" numeric(10,0),
    geom public.geometry,
    cost double precision,
    arcs text
);


ALTER TABLE zone1.distance_matrix OWNER TO developer;

--
-- Name: nodes; Type: TABLE; Schema: zone1; Owner: developer; Tablespace: 
--

CREATE TABLE nodes (
    gid integer NOT NULL,
    id numeric(10,0),
    elev numeric,
    zone smallint,
    long double precision,
    lat double precision,
    geom public.geometry(Point,4326),
    geom3d public.geometry
);


ALTER TABLE zone1.nodes OWNER TO developer;

--
-- Name: nodes_gid_seq; Type: SEQUENCE; Schema: zone1; Owner: developer
--

CREATE SEQUENCE nodes_gid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE zone1.nodes_gid_seq OWNER TO developer;

--
-- Name: nodes_gid_seq; Type: SEQUENCE OWNED BY; Schema: zone1; Owner: developer
--

ALTER SEQUENCE nodes_gid_seq OWNED BY nodes.gid;


--
-- Name: pathway; Type: TABLE; Schema: zone1; Owner: developer; Tablespace: 
--

CREATE TABLE pathway (
    gid integer,
    name character varying(254),
    number numeric(10,0),
    geom public.geometry(LineString,4326),
    length2d double precision,
    geom3d public.geometry,
    length3d double precision,
    webencoded text
);


ALTER TABLE zone1.pathway OWNER TO developer;

--
-- Name: vertices; Type: TABLE; Schema: zone1; Owner: developer; Tablespace: 
--

CREATE TABLE vertices (
    gid integer NOT NULL,
    number numeric(10,0),
    index numeric(10,0),
    elev numeric,
    geom public.geometry(Point,4326),
    geom3d public.geometry
);


ALTER TABLE zone1.vertices OWNER TO developer;

--
-- Name: vertices_gid_seq; Type: SEQUENCE; Schema: zone1; Owner: developer
--

CREATE SEQUENCE vertices_gid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE zone1.vertices_gid_seq OWNER TO developer;

--
-- Name: vertices_gid_seq; Type: SEQUENCE OWNED BY; Schema: zone1; Owner: developer
--

ALTER SEQUENCE vertices_gid_seq OWNED BY vertices.gid;


SET search_path = pathway, pg_catalog;

--
-- Name: id; Type: DEFAULT; Schema: pathway; Owner: developer
--

ALTER TABLE ONLY pathway ALTER COLUMN id SET DEFAULT nextval('sentieri_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: pathway; Owner: developer
--

ALTER TABLE ONLY zone ALTER COLUMN id SET DEFAULT nextval('zone_id_seq'::regclass);


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


SET search_path = zone1, pg_catalog;

--
-- Name: gid; Type: DEFAULT; Schema: zone1; Owner: developer
--

ALTER TABLE ONLY nodes ALTER COLUMN gid SET DEFAULT nextval('nodes_gid_seq'::regclass);


--
-- Name: gid; Type: DEFAULT; Schema: zone1; Owner: developer
--

ALTER TABLE ONLY vertices ALTER COLUMN gid SET DEFAULT nextval('vertices_gid_seq'::regclass);


SET search_path = pathway, pg_catalog;

--
-- Name: diagram_pkey; Type: CONSTRAINT; Schema: pathway; Owner: developer; Tablespace: 
--

ALTER TABLE ONLY diagram
    ADD CONSTRAINT diagram_pkey PRIMARY KEY ("order", pathway);


--
-- Name: pathway_pk; Type: CONSTRAINT; Schema: pathway; Owner: developer; Tablespace: 
--

ALTER TABLE ONLY pathway
    ADD CONSTRAINT pathway_pk PRIMARY KEY (id);


--
-- Name: zone_name_key; Type: CONSTRAINT; Schema: pathway; Owner: developer; Tablespace: 
--

ALTER TABLE ONLY zone
    ADD CONSTRAINT zone_name_key UNIQUE (name);


--
-- Name: zone_pkey; Type: CONSTRAINT; Schema: pathway; Owner: developer; Tablespace: 
--

ALTER TABLE ONLY zone
    ADD CONSTRAINT zone_pkey PRIMARY KEY (id);


SET search_path = public, pg_catalog;

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


SET search_path = zone1, pg_catalog;

--
-- Name: nodes_pkey; Type: CONSTRAINT; Schema: zone1; Owner: developer; Tablespace: 
--

ALTER TABLE ONLY nodes
    ADD CONSTRAINT nodes_pkey PRIMARY KEY (gid);


--
-- Name: nodes_unique; Type: CONSTRAINT; Schema: zone1; Owner: developer; Tablespace: 
--

ALTER TABLE ONLY nodes
    ADD CONSTRAINT nodes_unique UNIQUE (id);


--
-- Name: vertices_pkey; Type: CONSTRAINT; Schema: zone1; Owner: developer; Tablespace: 
--

ALTER TABLE ONLY vertices
    ADD CONSTRAINT vertices_pkey PRIMARY KEY (gid);


SET search_path = pathway, pg_catalog;

--
-- Name: index_aree; Type: INDEX; Schema: pathway; Owner: developer; Tablespace: 
--

CREATE INDEX index_aree ON zone USING gist (geom);


SET search_path = public, pg_catalog;

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
