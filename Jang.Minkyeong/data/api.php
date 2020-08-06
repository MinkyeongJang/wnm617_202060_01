<?php


include "auth.php";
function makeConn() {
	try {
		return new PDO(...PDOauth());
	} catch (PDOException $e) {
		die('{"error":"' . $e->getMessage() . '"}');
	}
}

function print_p($d) {
	echo "<pre>",print_r($d),"</pre>";
}

/* $r = PDO result */
function fetchAll($r) {
	$a = [];
	while($row = $r->fetch(PDO::FETCH_OBJ)) $a[] = $row;
	return $a;
}

/*
$c = connection
$ps = prepared statement
$p = parameters
*/
function makeQuery($c,$ps,$p) {
	try{
		if(count($p)) {
			$stmt = $c->prepare($ps);
			$stmt->execute($p);
		} else {
			$stmt = $c->query($ps);
		}
		$r = fetchAll($stmt);

		return [
			// "statement"=>$ps,
			// "params"=>$p,
			"result"=>$r
		];
	} catch (PDOException $e) {
		return ["error"=>"Query Failed: ".$e->getMessage()];
	}
}



function makeUpload($file,$folder) {
	$filename = microtime(true) . "_" .
		$_FILES[$file]['name'];

	if(@move_uploaded_file(
		$_FILES[$file]['tmp_name'],
		$folder.$filename
	)) return ["result"=>$filename];
	else return [
		"error"=>"File Upload Failed",
		"_FILES"=>$_FILES,
		"filename"=>$filename
	];
}



function makeStatement($data) {
	$c = makeConn();
	$t = $data->type;
	$p = $data->params;
	
	switch($t) {
		case "users_all" : return makeQuery($c,"SELECT * FROM `track_users`",[]);
		case "animals_all" : return makeQuery($c,"SELECT * FROM `track_animals`",[]);
		case "locations_all" : return makeQuery($c,"SELECT * FROM `track_locations`",[]);

		case "user_by_id" : return makeQuery($c,"SELECT id,name,username,email,date_create,img FROM `track_users` WHERE `id`=?",$p);
		case "animal_by_id" : return makeQuery($c,"SELECT * FROM `track_animals` WHERE `id`=?",$p);
		case "location_by_id" : return makeQuery($c,"SELECT * FROM `track_locations` WHERE `id`=?",$p);

		case "animals_by_user_id" : return makeQuery($c,"SELECT * FROM `track_animals` WHERE `user_id`=?",$p);
		case "locations_by_animal_id" : return makeQuery($c,"SELECT * FROM `track_locations` WHERE `animal_id`=?",$p);


		case "check_signin":
			return makeQuery($c,"SELECT `id` FROM `track_users` WHERE `username`=? AND `password`=md5(?)",$p);


		case "recent_locations":
			return makeQuery($c,"SELECT
				a.*, l.*
				FROM `track_animals` a
				LEFT JOIN (
					SELECT * FROM `track_locations`
					ORDER BY `date_create` DESC
				) l
				ON a.id = l.animal_id
				WHERE a.user_id = ?
				GROUP BY l.animal_id
				",$p);

		case "animal_search" : return makeQuery($c,"SELECT *
			FROM `track_animals`
			WHERE (
				`name` LIKE ? OR
				`breed` LIKE ? OR
				`color` LIKE ?
			) AND user_id=?",$p);

		case "animal_search_recent" : return makeQuery($c,"SELECT
			a.*, l.*
			FROM `track_animals` a
			LEFT JOIN (
				SELECT * FROM `track_locations`
				ORDER BY `date_create` DESC
			) l
			ON a.id = l.animal_id
			WHERE (
				a.name LIKE ? OR
				a.breed LIKE ? OR
				a.color LIKE ?
			) AND a.user_id=?
			GROUP BY l.animal_id",$p);


		case "animal_filter" : return makeQuery($c,"SELECT *
			FROM `track_animals`
			WHERE (
				`$p[0]` LIKE ?
			) AND user_id=?",[$p[1],$p[2]]);



		// CRUD


		// INSERT STATEMENTS
		case "insert_user":
			$r = makeQuery($c,"SELECT `id` FROM `track_users` WHERE `username`=? OR `email`=?",[$p[0],$p[1]]);
			if(count($r['result'])) return ["error"=>"Username or Email already exists"];

			$r = makeQuery($c,"INSERT INTO
				`track_users`
				(`username`, `email`, `password`, `img`, `date_create`)
				VALUES
				(?, ?, md5(?), 'https://via.placeholder.com/400/?text=USER', NOW())
				",$p);
			if(isset($r['error'])) return $r;
			return ["result"=>$c->lastInsertId()];

		case "insert_animal":
			$r = makeQuery($c,"INSERT INTO
				`track_animals`
				(`user_id`,`name`, `breed`, `color`, `description`, `img`, `date_create`)
				VALUES
				(?, ?, ?, ?, ?, 'https://via.placeholder.com/400/?text=ANIMAL', NOW())
				",$p);
			if(isset($r['error'])) return $r;
			return ["result"=>$c->lastInsertId()];

		case "insert_location":
			$r = makeQuery($c,"INSERT INTO
				`track_locations`
				(`animal_id`,`lat`, `lng`, `description`, `photo`, `icon`, `date_create`)
				VALUES
				(?, ?, ?, ?, 'https://via.placeholder.com/400/?text=LOCATION', 'https://via.placeholder.com/40/?text=ICON', NOW())
				",$p);
			if(isset($r['error'])) return $r;
			return ["result"=>$c->lastInsertId()];




		// UPDATE STATEMENTS
		case "update_user":
			$r = makeQuery($c,"UPDATE
				`track_users`
				SET
					`name`=?,
					`username`=?,
					`email`=?
				WHERE `id`=?
				",$p);
			return ["result"=>"success"];

		case "update_animal":
			$r = makeQuery($c,"UPDATE
				`track_animals`
				SET
				hcline	`name`=?,
						`breed`=?,
						`color`=?,
						`description`=?
				WHERE `id`=?
				",$p);
			return ["result"=>"success"];

		case "update_profile_image":
			$r = makeQuery($c,"UPDATE
				`track_users`
				SET `img`=?
				WHERE `id`=?
				",$p);
			return ["result"=>"success"];





		// DELETE STATEMENTS
		case "delete_animal":
			return makeQuery($c,"DELETE FROM `track_animals` WHERE `id`=?",$p);
		case "delete_location":
			return makeQuery($c,"DELETE FROM `track_locations` WHERE `id`=?",$p);




		default: return ["error"=>"No matched type"];
	}
}

if(!empty($_FILES)) {
	$r = makeUpload("image","../uploads/");
	die(json_encode($r));
}



$data = json_decode(file_get_contents("php://input"));

echo json_encode(
	makeStatement($data),
	JSON_NUMERIC_CHECK
);