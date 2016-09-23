--
-- Name: gcb_pathtype; Type: TYPE; Schema: public; Owner: developer
--

CREATE TYPE gcb_pathtype AS (
	status integer,
	cause text,
	anchor_from double precision[],
	anchor_to double precision[],
	cost double precision,
	path text
);

--
-- Name: _gcb_create_matrix(text); Type: FUNCTION; Schema: public; Owner: developer
--

CREATE FUNCTION _gcb_create_dm_matrix(zone text) RETURNS character varying
    LANGUAGE plpgsql
    AS $$DECLARE
    c record;
    r record;
    sql text;
    sqlarcs text;
    i integer;
BEGIN
    i := 0;
    for c in execute 'select "source", target" from zone'||zone||'.dm ' loop
        sqlarcs  := 'select "id", "from"::integer as source, "to"::integer as target, '
                || ' "length"::float as cost, x1, y1, x2, y2 from zone'||zone||'.arcs ';
        sql := ' select st_linemerge(st_collect(b.geom)) as geom , count(a.id2) as count, string_agg(a.id2::text,''_'') as arcs, '
            || ' string_agg(a.id1::text,''_'') as nodes, sum(a.cost) as cost from '
            || '( select * from pgr_astar ('||quote_literal(sqlarcs)||', '|| c."start" || ', ' || c."end" 
            || ', false, false ) ) a, zone'||zone||'.arcs b where b.id = a.id2 ';
        for r in execute sql loop
            execute 'update zone'||zone||'.dm set nodes = '||quote_literal(r.nodes::text)||' , '
                || ' arcs = '||quote_literal(r.arcs::text)||', count = ' || r."count" || ', cost = ' || r."cost" || ' where "start" = '
                ||c."start"||' and "end" = '||c."end";
        i := i + 1;
        end loop;
    end loop;
    return i || 'rows added to distance matrix in the schema "zone' || zone || '"';
 END$$;


--
-- Name: _gcb_directions(geometry, geometry); Type: FUNCTION; Schema: public; Owner: developer
--

CREATE FUNCTION _gcb_directions(source geometry, target geometry) RETURNS SETOF record
    LANGUAGE plpgsql
    AS $$DECLARE
    
    dist float[4];
    cost float [4];
    arcs text [4];
    nodes text [4];
    num integer[4];
    perc1 float;
    perc2 float;
    isreverse bool[4];
    r record;
    sql text;
    sql_path text;
    sql_from text;
    sql_to text;
    arcfrom record;
    arcto record;
    zone record;
    spezzate text[8];
    found_path integer;


    _reverse bool;
    _count integer;
    _cost float;
    _path text[8];
    _arcs text;
    _nodes text;
    _from geometry;
    _to geometry;  
BEGIN
    --- La funzione restituisce records con la struttura:
    --- ( 
    ---   ord: integer 'ordinamento archi'
    ---   id: arcid,  'arc id'
    ---   sentieri: text, 'id dei sentieri di cui fa parte l'arco'
    ---   nodes: text 'i nodi del path separati da _ '
    ---   arcs: text 'gli archi del path separati da _ '
    ---   path: text, 'geom web encoded dell'arco'
    ---   msg_direct : messaggio per l'arco in entrata da from 
    ---   msg_reverse : messaggio per l'arco in entrata da to
    --- )
    --- get area che contiene entrambe le geometrie ST_Contains
    select id from pathway.zone where st_contains( geom, source) and  st_contains( geom, target) into zone;
    if ( zone.id is NOT NULL ) 
    then
        --gcbpathtype
        dist[0] = -1; dist[1] = -1;
        --- distance 2d dei punti source e target dagli archi 
        sql = 'select *, st_distance ( st_transform(geom,3857), st_transform( st_geomfromtext( ''' ||  st_astext(source) || ''',4326),3857)) as d0,'
            ||         ' st_distance ( st_transform(geom,3857), st_transform( st_geomfromtext( ''' ||  st_astext(target) || ''',4326),3857)) as d1 '
            || ' from zone' || zone.id || '.arcs ';
        --- verifica archi prossimi a form e to
        for r in execute sql 
        loop
            if ( dist[0] = -1 or r.d0 < dist[0] ) 
            then
                dist[0] = r.d0;
                arcfrom = r;
            end if; 
            if ( dist[1] = -1 or r.d1 < dist[1] ) 
            then
                dist[1] = r.d1;
                arcto = r;
            end if; 
        end loop;

        cost = '{-1,-1,-1,-1}';
        --- se sono stati trovati archi
        if ( arcto.id is NOT NULL and arcfrom.id is NOT NULL )
        then
            
            --- calcolo porzioni arco e pti form e to
            perc1 = ST_Line_Locate_Point(arcfrom.geom, source);
            perc2 = ST_Line_Locate_Point(arcto.geom, target);

            dist[0] = arcfrom.length * perc1;
            dist[1] = arcfrom.length * ( 1 - perc1 );
            dist[2] = arcto.length * perc2;
            dist[3] = arcto.length * ( 1 - perc2 );

            spezzate[0] = googleencodeline( st_reverse( st_Line_Substring(arcfrom.geom, 0 , perc1 ) ) );
            spezzate[1] = googleencodeline( st_Line_Substring(arcfrom.geom, perc1, 1 ) );
            spezzate[2] = googleencodeline( st_Line_Substring(arcto.geom, 0 , perc2 ) );
            spezzate[3] = googleencodeline( st_reverse( st_Line_Substring(arcto.geom, perc2, 1 ) ) );
            _from = ST_LineInterpolatePoint(arcfrom.geom,perc1::float);
            _to = ST_LineInterpolatePoint(arcto.geom,perc2::float);
            
            --- se source e target sono ancorati sullo stesso arco crea il path 
            if ( arcto.id = arcfrom.id )
            then
                if ( perc1 < perc2 )
                then
                    _path[0] = st_line_substring(arcfrom.geom,perc1,perc2);
                    _cost = dist[2] - dist[0];
                    --- da capire posizionamento ancora rispetto arco : dx o sx
                    _msg = 'Arrivare al sentiero xx e proseguire verso (dx o sx) per ' || cost || 'metri. ';
                else
                    _path[0] = st_reverse(st_line_substring(arcfrom.geom,perc2,perc1);
                    _cost = dist[3] - dist[1];
                    --- da capire posizionamento ancora rispetto arco inverso: dx o sx e nome sentiero
                    _msg = 'Arrivare al sentiero xx e proseguire verso (dx o sx) per ' || cost || 'metri. ';
                end if;
                --- esce rstituendo la linea con i punti ordinati
                sql = 'select 0 as order, a.id, a.sentieri, '' as arcs, '' as nodes,''' 
                        || googleencodeline(_path0) || ''' as path, ''' || _msg || '''::text, ' 
                        || _cost || ' as cost from zone' || zone.id || '.arcs a  where a.id = ' || arcto.id; 
                return query execute sql;
            --- se source e target non sono archi adiacenti cerca il path nella distance matrix 
            else if (   ( arcto."to" != arcfrom."from" and arcto."from" != arcfrom."from" )   
                    or ( arcto."to" != arcfrom."to"   and arcto."from" != arcfrom."to"   ) )
            then
                --- cerca tutti i path nella distance matrix
                sql = 'select * from zone' || zone.id || '.dm where '
                   || ' ( "start" = ' || arcto."from"  || ' or "start" = ' || arcfrom."from" 
                   || ' or "start" = ' || arcto."to" || ' or "start" = ' || arcfrom."to" || ')' 
                   || ' and ( "end" = ' || arcto."from"  || ' or "end" = ' || arcfrom."from" 
                   || ' or "end" = ' || arcto."to" || ' or "end" = ' || arcfrom."to" || ')'; 
                --- assegna i valori per i soli 4 validi
                for r in execute sql 
                loop 
                    if (  ( r."start" = arcfrom."from" and r."end"   = arcto."from") 
                       or ( r."end"   = arcfrom."from" and r."start" = arcto."from"   ) ) 
                    then
                        cost[0] = dist[0] + r.cost + dist[2];
                        arcs[0] = r.arcs;
                        num[0] = r."count";
                        nodes[0] = r.nodes;
                        _path[0] = spezzate[0];
                        _path[1] = spezzate[2];
                        if ( r."start" = arcfrom."from" )
                        then
                            isreverse[0] = false;
                        else 
                            isreverse[0] = true;
                        end if;
                    else if (  ( r."start" = arcfrom."from" and r."end" = arcto."to" ) 
                            or ( r."end" = arcfrom."from" and r."start" = arcto."to" ) ) 
                    then
                        cost[1] = dist[0] + r.cost + dist[3];
                        arcs[1] = r.arcs;
                        nodes[1] = r.nodes;
                        num[1] = r."count";
                        _path[2] = spezzate[0];
                        _path[3] = spezzate[3];
                        if ( r."start" = arcfrom."from" )
                        then
                            isreverse[1] = false;
                        else 
                            isreverse[1] = true;
                        end if;
                    else if ( ( r."end" = arcto."from" and  r."start" = arcfrom."to")
                           or ( r."start" = arcto."from" and  r."end" = arcfrom."to") ) 
                    then
                        cost[2] = dist[2] + r.cost + dist[1];
                        arcs[2] = r.arcs;
                        nodes[2] = r.nodes;
                        num[2] = r."count";
                        _path[4] = spezzate[1]; 
                        _path[5] = spezzate[2];
                        if ( r."start" = arcfrom."to" )
                        then
                            isreverse[2] = false;
                        else 
                            isreverse[2] = true;
                        end if;
                    else if ( ( r."end" = arcto."to" and  r."start" = arcfrom."to" ) 
                           or ( r."start" = arcto."to" and  r."end" = arcfrom."to" ) )
                    then
                        cost[3] = dist[1] + r.cost + dist[3];
                        arcs[3] = r.arcs;
                        nodes[3] = r.nodes;
                        num[3] = r."count";
                        _path[6] = spezzate[1]; 
                        _path[7] = spezzate[3];
                        if ( r."start" = arcfrom."to" )
                        then
                            isreverse[3] = false;
                        else 
                            isreverse[3] = true;
                        end if;
                    end if;end if;end if;end if;
                end loop;
            --- scelta fra le 4 soluzioni
                dist[0] = arcfrom.length * perc1;
                dist[1] = arcto.length * perc2;
                found_path = 0;
                _cost = cost[0];
                _arcs = arcs[0];
                _nodes = nodes[0];
                _count = num[0];
                _reverse = isreverse[0];
                if ( _cost < 0 or _cost > cost[1] ) 
                then 
                    found_path = 1;
                    _cost = cost[1];
                    _arcs = arcs[1];
                    _nodes = nodes[1];
                    dist[0] = arcfrom.length * perc1;
                    dist[1] = arcto.length * (1-perc2);
                    _count = num[1];
                    _reverse = isreverse[1];
                end if;
                if ( _cost < 0 or _cost > cost[2] ) 
                then 
                    found_path = 2;
                    _cost = cost[2];
                    _arcs = arcs[2];
                    _nodes = nodes[2];
                    _count = num[2];
                    _reverse = isreverse[2];
                    dist[0] = arcfrom.length * (1-perc1);
                    dist[1] = arcto.length * (perc2);
                end if;
                if ( _cost < 0 or _cost > cost[3] ) 
                then 
                    found_path = 3;
                    _cost = cost[3];
                    _arcs = arcs[3];
                    _nodes = nodes[3];
                    _count = num[3];
                    _reverse = isreverse[3];
                    dist[0] = arcfrom.length * (1-perc1);
                    dist[1] = arcto.length * (1-perc2);
                end if;
            
                --- se il costo è > 0  è stato selezionato un path quindi restituisce gli archi trovati
                if ( _cost > 0 ) 
                then 
                    --- verifica il reverse 
                    sql = 'select 0 as ord, a.id as arcid, a.from as arcform, a.to as arcto, a.sentieri as arcsentieri, ''' || _arcs || '''::text as patharcs, ''' || _nodes || '''::text as pathnodes,''' 
                            || _path[ found_path * 2 ] || '''::text as path, '' go to pathway xx then  walk for ... to ...''::text as msg, ' 
                            || dist[0] || ' as cost from zone' || zone.id || '.arcs a where a.id = ' || arcfrom.id; 
                    return query execute sql;
                    --- verifica il reverse 
                    if ( _reverse )
                    then
                        for i in 2 .. _count+1 loop
                            sql = 'select ' || (i - 1)::text || ' as ord, a.id as arcid,'
                                    || ' case when a.from = ' || split_part( _nodes, '_' , i) || ' then a.from else a.to end as arcfrom, '
                                    || ' case when a.from = ' || split_part( _nodes, '_' , i) || ' then a.to else a.from end as arcto, '
                                    || ' a.sentieri, ''' || _arcs || '''::text as arcs, ''' || _nodes || '''::text as nodes,'
                                    || ' case when a.from = ' || split_part( _nodes, '_' , i) || ' then a."direct" else a."reverse" end as path,'
                                    || ' b.msg, a.length::numeric as cost' 
                                    || ' from zone' || zone.id || '.arcs a, zone' || zone.id || '.directions_access_msg b '
                                    || ' where a.id = b.id and a.id = ' ||  split_part( _arcs, '_' , i);
                            return query execute sql;            
                        end loop;
                    else
                        for i in reverse _count+1 .. 2 loop
                            sql = 'select ' || (_count - i)::text || ' as ord, a.id as arcid,'
                                    || ' case when a.from = ' || split_part( _nodes, '_' , i) || ' then a.from else a.to end as arcfrom, '
                                    || ' case when a.from = ' || split_part( _nodes, '_' , i) || ' then a.to else a.from end as arcto, '
                                    || ' a.sentieri, ''' || _arcs || '''::text as arcs, ''' || _nodes || '''::text as nodes,'
                                    || ' case when a.from = ' || split_part( _nodes, '_' , i) || ' then a."direct" else a."reverse" end as path,'
                                    || ' b.msg, a.length::numeric as cost' 
                                    || ' from zone' || zone.id || '.arcs a, zone' || zone.id || '.directions_access_msg b '
                                    || ' where a.id = b.id and a.id = ' ||  split_part( _arcs, '_' , i);
                            return query execute sql;            
                        end loop;
                    end if;
                    
                    --return query execute sql;
                    sql = 'select ' || (_count + 1 )::text || ' as ord, a.id as arcid, a.from as arcform, a.to as arcto, a.sentieri, ''' || _arcs || '''::text as arcs, ''' || _nodes || '''::text as nodes,''' 
                            || _path[ found_path * 2 + 1 ] || '''::text as path, '' take xxx on the xxx then walk for xxx meters you are near to your destination ''::text as msg, ' 
                            || dist[1] || ' as cost from zone' || zone.id || '.arcs a  where a.id = ' || arcto.id; 
                    return query execute sql;

                end if;
            else   --- archi adiacenti 
                if ( arcto."from" == arcfrom."from" )
                then 
                    _path[0] = spezzate[0]; 
                    _path[1] = spezzate[2];
                    dist[0] = arcfrom.length * perc1;
                    dist[1] = arcto.length * perc2;
                else if ( arcto."to" == arcfrom."from" ) 
                then 
                    _path[0] = spezzate[0]; 
                    _path[1] = spezzate[3];
                    dist[0] = arcfrom.length * perc1;
                    dist[1] = arcto.length * (1-perc2);
                else if ( arcto."to" == arcfrom."to" )
                then 
                    _path[0] = spezzate[1]; 
                    _path[1] = spezzate[3];
                    dist[0] = arcfrom.length * (1-perc1);
                    dist[1] = arcto.length * (1-perc2);
                else if ( arcto."from" == arcfrom."to"   ) 
                then 
                    _path[0] = spezzate[1]; 
                    _path[1] = spezzate[2];
                    dist[0] = arcfrom.length * (1-perc1);
                    dist[1] = arcto.length * perc2;
                end if;end if;end if;end if;   
                sql = 'select 0 as order, a.id, a.sentieri,  ''' || _arcs || '''::text as arcs, ''' || _nodes || '''::text as nodes,''' 
                        || _path[0] || '''::text as path, ''to add versus''::text as msg, ' 
                        || dist[0] || ' as cost from zone' || zone.id || '.arcs a where a.id = ' || arcfrom.id; 

                return query execute sql;        
                sql = 'select 1 as order, a.id, a.sentieri,  ''' || _arcs || ''' as arcs, ''' || _nodes || ''' as nodes,''' 
                        || _path[1] || ''' as path, ''to add versus'' as msg, ' 
                        || dist[1] || ' as cost from zone' || zone.id || '.arcs a  where a.id = ' || arcto.id; 
                return query execute sql;
            end if;end if; ---- fine verifica archi
        end if; ---- fine verifica ancoraggio    
    end if; --- fine verifica zona 
    ---- return nothing
End
$$;




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
