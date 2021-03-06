<?php
header("Access-Control-Allow-Origin: *");
header('Access-Control-Allow-Methods', 'POST, GET, OPTIONS, DELETE, PUT');
// Access-Control headers are received during OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST,OPTIONS");

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers:        {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    exit(0);
}
ini_set('display_errors', 1);

require_once __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../src/app.php';
require __DIR__.'/../config/prod.php';
require __DIR__.'/../src/controllers.php';

use Silex\Application, Symfony\Component\HttpFoundation\Request;

$app->before(function(Request $request, Application $app) {
    if (($request->isMethod('POST') || $request->isMethod('OPTIONS') || $request->isMethod('PUT')) && strpos($request->headers->get('Content-Type'), 'application/json') === 0) {
        $request->request->set('data', json_decode($request->getContent(), true) ?: []);
    }
});

$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/development.log',
));
// set debug mode
$app['debug'] = true;




$app->before(function(Request $request, Application $app) {
    if (($request->isMethod('POST') || $request->isMethod('OPTIONS') || $request->isMethod('PUT')) && strpos($request->headers->get('Content-Type'), 'application/json') === 0) {
        $request->request->set('data', json_decode($request->getContent(), true) ?: []);
    }
});


$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'dbs.options' => array (
        'localhost' => array(
            'driver'    => 'pdo_mysql',
            'host'      => 'localhost',
            'dbname'    => 'gc_db',
            'user'      => 'root',
            'password'  => '1234',
            'charset'   => 'utf8',
        )
    ),
));

function getDataFromRequest(Request $request)
{
    return $request->request->get('data');
}


/**
 * @api {get} /auth/:login/:mot_pass Authentification d'un utilisateur
 * @apiName auth
 * @apiGroup Authentification
 *
 * @apiParam {String} login Nom d'utilsateur.
 * @apiParam {String} mot_pass Mot de passe.
 *
 * @apiSuccess {String} Objet JSON avec "operation" : "ok"
 * @apiSuccessExample Success-Response:
 *     {
 *       "operation": "ok"
 *     }
 * @apiError MotPasseIncorrect 'Mot de passe incorrect !' si le mot de passe est incorrect.
 * @apiError UtilisateurInexistant 'Utilisateur x n'existe pas !' si le nom d'utilisateur ne se trouve pas dans la table des utilisateurs.
 * @apiErrorExample Error-Response:
 *     {
 *			"operation": "ko",
 *			"erreur": "MotPasseIncorrect",
 *			"message": "Mot de passe incorrect !"
 *     }
 */
$app->get('/auth/{login}/{mot_pass}', function ($login,$mot_pass) use ($app) {
    $sql = "SELECT * FROM utilisateur WHERE login = ?";
    $user = $app['db']->fetchAssoc($sql,array($login));
	
    if(!$user){
    	$reponse = array('operation' => 'ko' , 'erreur' => "Utilisateur "  . $login .  "n'existe pas !");
		return $app->json($reponse);
    } else if($user['mot_passe']!=$mot_pass){
    	$reponse = array('operation' =>'ko','erreur'=> 'Mot de passe incorrect !');    		
		return  $app->json($reponse);
    }
	
    $reponse = array('operation' =>'ok');
	return  $app->json($reponse);
});

/**

 * @api {post} /saveCourrier Enregistrement/Modification d'un courrier
 * @apiName saveCourrier
 * @apiGroup Courrier
 *
 * @apiParam {ObjetJSON} Objet JSON avec les paramétres suivants :
 *     {
 * 			"titre" : Titre (Objet) du courrier.
 * 			"description" : Un texte descriptif du courrier, une sorte de résumé du contenu du courrier.
 * 			"dateCourrier" : Date du courrier. si le courrier est un courrier arrivée, il s'agit de la date de réception du courrier. si le courrier est un courrier départ, il s'agit de la date d'envoi. Le format utilisé est "JJ/MM/AAAA".
 * 			"type" : Type du courrier. Ne peut prendre que une des deux valeurs suivantes : 'Courrier Arrivée' / 'Courrier Départ'.
 * 			"nature" : Nature du courrier. peut prendre une des valeurs suivantes : 'Lettre' / 'Fax' / 'E-mail' / 'Colis' / 'Autre'.
 * 			"reference" : Référence du courrier. c'est une référence unique associé au courrier en vue de l'identifier.
 * 			"idEntite" : ID de l'entite concerné par le courrier. Si le courrier est un courrier arrivée, c'est l'id de l'entité destinataire. Si le courrier est un courrier départ, c'est l'id de l'entité source. 
 *     }
 * @apiSuccess {String} Objet JSON avec "operation" : "ok".
 * @apiSuccessExample Success-Response:
 *     {
 *       "operation": "ok"
 *     }
 * @apiError ValeurInvalide 'Valeur du champ x incorrecte !' si une valeur d'une des champs envoyés à ce service n'est pas valide.
 * @apiError ChampObligatoire 'Le champ x est obligatoire !' si le champ n'est pas renseigné.
 * @apiErrorExample Error-Response:
 *     {
 *			"operation": "ko",
 *			"erreur": "ValeurInvalide",
 *			"message": "Valeur du champ x incorrecte !"
 *     }
 */
$app->post('/saveCourrier', function (Request $request) use ($app) {
	$courrier=getDataFromRequest($request);
	
	if (trim($courrier['titre']) == '') return  $app->json(array('operation' =>'ko','erreur'=> 'ChampObligatoire', 'message'=> 'Le champ titre est obligatoire !'));
	if (trim($courrier['description']) == '') return  $app->json(array('operation' =>'ko','erreur'=> 'ChampObligatoire', 'message'=> 'Le champ description est obligatoire !'));
	if (trim($courrier['datecourrier']) == '') return  $app->json(array('operation' =>'ko','erreur'=> 'ChampObligatoire', 'message'=> 'Le champ date courrier est obligatoire !'));
	if (trim($courrier['type']) == '') return  $app->json(array('operation' =>'ko','erreur'=> 'ChampObligatoire', 'message'=> 'Le champ type est obligatoire !'));
	if (trim($courrier['nature']) == '') return  $app->json(array('operation' =>'ko','erreur'=> 'ChampObligatoire', 'message'=> 'Le champ nature est obligatoire !'));
	if (trim($courrier['adresse']) == '') return  $app->json(array('operation' =>'ko','erreur'=> 'ChampObligatoire', 'message'=> 'Le champ adresse est obligatoire !'));
	if (trim($courrier['reference']) == '') return  $app->json(array('operation' =>'ko','erreur'=> 'ChampObligatoire', 'message'=> 'Le champ reference est obligatoire !'));
	if (trim($courrier['entite']['id']) == '') return  $app->json(array('operation' =>'ko','erreur'=> 'ChampObligatoire', 'message'=> 'Le champ idEntite est obligatoire !'));
	if (is_numeric($courrier['entite']['id']) == false) return  $app->json(array('operation' =>'ko','erreur'=> 'ValeurInvalide', 'message'=> 'Valeur de idEntite est invalide !'));

	
   	$sql = "REPLACE INTO courrier(id, titre,description ,datecourrier ,type ,nature ,adresse ,reference ,id_entite) VALUES (:id, :titre, :description, :dateCourrier, :type, :nature, :adresse, :reference, :idEntite)";
    $query = $app['db']->prepare($sql);
    $query->execute(array(
			"id" => $courrier['id'],
            "titre" => $courrier['titre'], 
            "description" => $courrier['description'],
            "dateCourrier" => $courrier['datecourrier'],
            "type" => $courrier['type'],
            "nature" => $courrier['nature'],
            "adresse" => $courrier['adresse'],
            "reference" => $courrier['reference'],
            "idEntite" => $courrier['entite']['id']			
            ));

            $courrier['id']= $courrier['id']!=0?$courrier['id']:$app['db']->lastInsertId();
	foreach ($courrier['documents'] as $doc) {
		$sql = "REPLACE INTO document(id,id_courrier, fichier) VALUES (:id,:idCourrier, :fichier)";
		$query = $app['db']->prepare($sql);
		$query->execute(array(
                "id" => $doc['id'],
				"idCourrier" => $courrier['id'], 
				"fichier" => $doc['fichier']		
				));		
    }	
    $app['db']->delete("diffusion", array("id_courrier" => $courrier['id']));
    foreach ($courrier['destinataires'] as $dest) {
		$sql = "INSERT INTO diffusion(id,id_courrier,id_entite,action) VALUES (:id,:idCourrier, :idEntite,:action)";
		$query = $app['db']->prepare($sql);
		$query->execute(array(
                "id" => $dest['id'],
				"idCourrier" => $courrier['id'], 
				"idEntite" => $dest['entite']['id'],
				"action" => $dest['action']
				));		
	}		
    $reponse = array('operation' =>'ok');
    $reponse = array(
        'operation' =>'ok',
        'courrier'=>$courrier,
        'message'=> $courrier['id']?'Modification réussi':'Ajout réussi'
        );
	return $app->json($reponse);
});

/**
 * @api {post} /saveDocument Enregistrement d'un document scanné sur le serveur
 * @apiName saveDocument
 * @apiGroup Courrier
 *
 * @apiParam {Base64String} body Le contenu du document scanné, encodé dans le format Base64.
 *
 * @apiSuccess {String} Objet JSON avec "operation" : "ok".
 * @apiSuccess {String} fichier le nom du fichier sur le serveur du document scanné.
 * @apiSuccessExample Success-Response:
 *     {
 *      	"operation": "ok",
 *			"fichier" : "courrier-scan-2018-01-01-13-28-00.png"
 *     }
 * @apiError FormatBase64Invalide 'Format Base64 invalide du document !' si le contenu envoyé du document ne respecte pas le format Base64.
 * @apiErrorExample Error-Response:
 *     {
 *			"operation": "ko",
 *			"erreur": "ValeurInvalide",
 *			"message": "Format Base64 invalide du document !"
 *     }
 */
$app->post('/saveDocument', function () use ($app) {
    $data = base64_decode(file_get_contents('php://input'));

    if ($data === false) {
        $reponse = array('operation' =>'ko','erreur'=> 'FormatBase64Invalide', 'message'=> 'FormatBase64Invalide');
		return  $app->json($reponse);
    }
	$docFileName = "courrier-scan-" . (new DateTime())->format('Y-m-d-H-i-s') . ".png";
	file_put_contents('../../gc-ocr-module/images/' . $docFileName, $data);
	
	$reponse = array('operation' =>'ok', 'fichier' => $docFileName);
	return $app->json($reponse);
});

/**
 * @api {get} /rechercherCourrier/:query Recherche d'un courrier par mots clés
 * @apiName rechercherCourrier
 * @apiGroup Courrier
 *
 * @apiParam {String} query Mot clés à chercher dans le courrier stocké dans la base de données
 *
 * @apiSuccess {String} Objet JSON avec "operation" : "ok".
 * @apiSuccess {Array} resultat Un tableau JSON de resultat, dont chaque élément du tableau est un courrier qui correspond au mot(s) clé(s) utilisés dans la recherche.
 * @apiSuccessExample Success-Response:
 *     {
 *      	"operation": "ok",
 *			"resultat" : [{
 *							"reference" : "FAX/23/2018",
 *							"titre": "Lancement du concours de recrutement des techniciens 3éme grade",
 *							"type": "Courrier Départ",
 *							"date": "20/01/2018",
 *							"nature": "Fax"
 *							"idEntite": "5",
 *							"nomEntite": "DRH"
 *							},
 *							...
 *							]
 *     }
 */
$app->get('/rechercherCourrier/{query}', function ($query) use ($app) {
	
	$encodedQuery = $query;
	if ($query != '*:*') {
		$encodedQuery = urlencode($query);
	} 
	
	$solrResponse = file_get_contents('http://localhost:8983/solr/courrier-data/select?q=' . $encodedQuery);
	$solrJson = json_decode($solrResponse);
	
	$docs = $solrJson->{'response'}->{'docs'};
	$idCourrierArray = array();
	foreach ($docs as $doc) {
		if (!in_array($doc->{'id_courrier'}[0], $idCourrierArray)) {
			array_push($idCourrierArray, $doc->{'id_courrier'}[0]);
		}
	}
	
	if (count($idCourrierArray) > 0) {
        $sql = "SELECT 
                    c.id,
                    c.titre,
                    c.description,
                    c.datecourrier,
                    c.type cType,
                    c.nature,
                    c.adresse,
                    c.reference,
                    c.id_entite,
                    e.id_parent,
                    e.type eType,
                    e.nom
                FROM
                    courrier c
                INNER JOIN entite e ON e.id = c.id_entite
                ORDER BY c.id
                ";
               /* WHERE c.id in (";
		$sep = "";
		foreach ($idCourrierArray as $e) {
			$sql .= $sep . $e;
			$sep = ',';
		}
		$sql .= ")";*/
        $courriers = $app['db']->fetchAll($sql, array());

        $document = $app['db']->fetchAll("SELECT id,id_courrier,fichier FROM document",array());
        $sql="SELECT
                d.id,
                d.responsable,
                d.action,
                d.delai,
                d.reponse,
                d.id_courrier,
                d.id_entite,
                e.id_parent,
                e.type,
                e.nom,
                d.id_instruction,
                i.libelle
            FROM
                diffusion d
            INNER JOIN entite e ON e.id = d.id_entite
            LEFT JOIN instruction i ON i.id = d.id_instruction
            ORDER BY d.action
            ";
        $diffusion = $app['db']->fetchAll($sql,array());
        $response = [];
        foreach ($courriers as $c) {
            $doc=array();
            foreach ($document as $d) {
                if($d['id_courrier']==$c['id']){
                    $doc[]= [
                        'id'=>$d['id'],
                        'fichier'=>$d['fichier'],
                        'id_courrier'=>$d['id_courrier']
                        ];
                }
            }
            $dest=array();
            foreach ($diffusion as $f) {
                if($f['id_courrier']==$c['id']){
                    $dest[]= [
                        'id'=>$f['id'],
                        'action'=>$f['action'],
                        'responsable'=>$f['responsable'],
                        'delai'=>$f['delai'],
                        'reponse'=>$f['reponse'],
                        'entite'=>[
                            'id'=>$f['id_entite'],
                            'nom'=>$f['nom'],
                            'type'=>$f['type'],
                            'id_parent'=>$f['id_parent']
                        ],
                        'instruction'=>[
                            'id'=>$f['id_instruction'],
                            'libelle'=>$f['libelle'],
                        ]
                    ];
                }
            }

            $response[] = [
                'id' => $c['id'],
                'titre' => $c['titre'],
                'description' => $c['description'],
                'datecourrier' => $c['datecourrier'],
                'type' => $c['cType'],
                'nature' => $c['nature'],
                'adresse' =>  $c['adresse'],
                'reference' => $c['reference'],
                'entite' =>[
                    'id'=>$c['id_entite'],
                    'id_parent'=>$c['id_parent'],
                    'type'=>$c['eType'],
                    'nom'=>$c['nom']
                ],
                'documents'=>$doc,
                'destinataires'=>$dest
                ];
        }
        
        foreach ($courriers as $u) {
            
        }
		$reponse = array('operation' =>'ok','resultat'=> $response);
		return  $app->json($reponse);
	} else {
		$reponse = array('operation' =>'ok','resultat'=> array());
		return  $app->json($reponse);
	}

});


/**
 * @api {get} /detailCourrier/:id Lire le détail d'un courrier à partir de son ID.
 * @apiName detailCourrier
 * @apiGroup Courrier
 *
 * @apiParam {Number} id ID du courrier.
 *
 * @apiSuccess {String} Objet JSON avec "operation" : "ok".
 * @apiSuccess {Objet} courrier Un objet JSON contenant toute les informations du courrier
 * @apiSuccessExample Success-Response:
 *     {
 *      	"operation": "ok",
 *			"courrier" : {
 *							"reference" : "FAX/23/2018",
 *							"titre": "Lancement du concours de recrutement des techniciens 3éme grade",
 *							"description": "La direction des ressources humaines lance un concours au profit des techniciens spécialisés ...",
 *							"type": "Courrier Départ",
 *							"adresse": "SNTL , Direction des ressources humaines Hay EL KAMRA, RABAT",
 *							"date": "20/01/2018",
 *							"nature": "Fax"
 *							"idEntite": "5",
 *							"nomEntite": "DRH"
 *							}
 *     }
 * @apiError IdInvalide 'Id invalide !' si l'id n'est pas une valeur numérique.
 * @apiError CourrierInexistant 'Le courrier avec id x est inexistant !' si l'id ne correspond à aucun courrier au niveau de la table du courrier.
 * @apiErrorExample Error-Response:
 *     {
 *			"operation": "ko",
 *			"erreur": "CourrierInexistant",
 *			"message": "Le courrier avec id 5 est inexistant !"
 *		}
 */
$app->get('/detailCourrier/{id}', function ($id) use ($app) {
	$reponse = array('operation' =>'ko','erreur'=> 'NOT_IMPLEMENTED');
	return  $app->json($reponse);	
});

/**
 * @api {get} /supprimerCourrier/:id Supprimer un courrier à partir de son ID.
 * @apiName supprimerCourrier
 * @apiGroup Courrier
 *
 * @apiParam {Number} id ID du courrier.
 *
 * @apiSuccess {String} Objet JSON avec "operation" : "ok".
 * @apiSuccessExample Success-Response:
 *     {
 *      	"operation": "ok",
 *     }
 * @apiError IdInvalide 'Valeur de id est invalide !' si l'id n'est pas une valeur numérique.
 * @apiError CourrierInexistant 'Le courrier avec id x est inexistant !' si l'id ne correspond à aucun courrier au niveau de la table du courrier.
 * @apiErrorExample Error-Response:
 *     {
 *			"operation": "ko",
 *			"erreur": "CourrierInexistant",
 *			"message": "Le courrier avec id 5 est inexistant !"
 *		}
 */
$app->post('/supprimerCourrier/', function (Request $request) use ($app) {	
  /*  (is_numeric($id) == false) return  $app->json(array('operation' =>'ko','erreur'=> 'IdInvalide', 'message'=> 'Valeur de id est invalide !'));

	$sql = "DELETE FROM courrier where id=:id";
    $query = $app['db']->prepare($sql);
    $query->execute(array(
            "id" => $id		
            ));
	
	if ($query->rowCount() == 1) {
		$reponse = array('operation' =>'ok');
		return $app->json($reponse);
	} else {
		$reponse = array('operation' =>'ko','erreur'=> 'CourrierInexistant', 'message'=> 'Courrier avec id ' . $id . ' est inexistant !' );
		return  $app->json($reponse);
    }*/
    

    $courrier=getDataFromRequest($request);
    $app['db']->delete("document", array("id_courrier" => $courrier['id']));
    $app['db']->delete("courrier", array("id" => $courrier['id']));
    $reponse = array(
        'operation' =>'ok',
        'courrier'=>$courrier,
        'message'=> 'Suppression réussi'
        );
   
    return  $app->json($reponse);
});


/**
 * @api {get} /listUsers Affichage de la liste des utilisateurs
 * @apiName listUsers
 * @apiGroup Utilisateurs
 * 
 * @apiSuccess {String[]} des Objets Utilisateurs dans un Objet JSON
 * @apiSuccessExample Success-Response:
 *     {
 *       {
 *          id: 1, 
 *          login: "login", 
 *          nom: "nom", 
 *          prenom: "prenom", 
 *          email: "email", 
 *          mot_passe: "", 
 *          role: "role", 
 *          entite: {
 *                      id:2,
 *                      id_parent:1,
 *                      type:"type",
 *                      nom:"nom"
 *                  }
 *      };
 *    }
 *@apiError ListeUtilisateursVide 'La liste des utilisateurs est vide' si aucun utilisateur n a été insérer.
 *@apiErrorExample Error-Response:
 *     {
 *			"operation": "ko",
 *			"erreur": "ListeUtilisateursVide",
 *			"message": "La liste des utilisateurs est vide. Aucun utilisateur n'est ajouté !"
 *     }
 */

$app->get('/listUsers/', function() use ($app){
    $sql = "
        SELECT
            u.id,
            u.login,
            u.nom,
            u.prenom,
            u.email,
            u.role,
            e.id idE ,
            e.id_parent,
            e.type,
            e.nom nomE
        FROM
            utilisateur u
        LEFT JOIN 
            entite e ON e.id = u.id_entite
        ";
    $users = $app['db']->fetchAll($sql,array());
    if(is_null($users)){
    	$response = array('operation' => 'ko' , 'erreur' => "La liste des utilisateurs est vide. Aucun utilisateur n'est ajouté !!");
		return $app->json($reponse);
    }
    $response = [];
    foreach ($users as $u) {
        $response[] = [
			'id' => $u['id'],
            'login' => $u['login'],
            'nom' => $u['nom'],
            'prenom' => $u['prenom'],
            'email' => $u['email'],
            'mot_passe' => '',
            'role' => $u['role'],
            'entite' =>[
                'id'=>$u['idE'],
                'id_parent'=>$u['id_parent'],
                'type'=>$u['type'],
                'nom'=>$u['nomE'],
            ]
        ];
    }
    return $app->json($response);
    });

/**
 * @api {post} /addUser/ Ajouter un utilisateur
 * @apiName addUser
 * @apiGroup Utilisateurs
 * 
 * @apiParam {String} login Login de l'utilsateur.
 * @apiParam {String} nom Nom de l'utilisateur.
 * @apiParam {String} prenom Prenom de l'utilisateur.
 * @apiParam {String} email Email de l'utilisateur.
 * @apiParam {String} mot_passe Mot de passe de l'utilisateur.
 * @apiParam {String} role Role de l'utilisateur.
 * @apiParam {number} id_entite Identifiant de l'entité.
 *
 * @apiSuccess {String} Objet JSON avec "operation" : "Ajout réussi"
 * @apiSuccessExample Success-Response:
 *     {
 *       "operation": "Ajout réussi"
 *     }
 * @apiError UtilisateurExiste 'L Utilisateur .$login existe déjà' si les données saisies concerne un utilisateur existant.
 * @apiErrorExample Error-Response:
 *     {
 *			"operation": "ko",
 *			"erreur": "UtilisateurExiste",
 *			"message": "L Utilisateur .$login existe déjà"
 *     }
 */

$app->post('/addUser/', function(Request $request) use ($app){
   $user=getDataFromRequest($request); 
   
   
   $data=$app['db']->fetchAssoc('SELECT * FROM utilisateur WHERE login=?', [(string) $user['login']]);
   
   if(!$data){
		$app['db']->insert('utilisateur', 
									array(
										'login' => $user['login'],
										'mot_passe'=> $user['mot_passe'],
										'nom'=> $user['nom'],
										'prenom'=> $user['prenom'],
										'email'=> $user['email'],
										'role'=> $user['role'],
										'id_entite'=> $user['entite']['id']
										)
		);   
		$user['id']=$app['db']->lastInsertId();
		
		$reponse = array(
						'operation' =>'ok',
						'user'=>$user,
						'message'=> 'Ajout réussi'
						);
		return  $app->json($reponse);   
	}
   
    $reponse = array(
				'operation' => 'ko' , 
				'erreur' => 'UtilisateurExiste',
				'message' => "Utilisateur " .$user['login']. " existant!!");
    return $app->json($reponse);                        
});


/**
 * @api {put} /updateUser/:id/:login/:nom/:prenom/:email/:mot_passe/:role/:id_entite Modifier un utilisateur
 * @apiName updateUser
 * @apiGroup Utilisateurs
 * 
 * @apiParam {number} id Identifiant de l'utilsateur.
 * @apiParam {String} login Login de l'utilsateur.
 * @apiParam {String} nom Nom de l'utilisateur.
 * @apiParam {String} prenom Prenom de l'utilisateur.
 * @apiParam {String} email Email de l'utilisateur.
 * @apiParam {String} mot_passe Mot de passe de l'utilisateur.
 * @apiParam {String} role Role de l'utilisateur.
 * @apiParam {number} id_entite Identifiant de l'entité.
 * 
 * @apiSuccess {String} Objet JSON avec "operation" : "Modification réussite"
 * @apiSuccessExample Success-Response:
 *     {
 *       "operation": "Modification réussite."
 *     }
 *
 */

$app->post('/updateUser/', function(Request $request) use ($app){
  $user=getDataFromRequest($request);
  $data=$app['db']->fetchAssoc('SELECT * FROM utilisateur WHERE id<>? AND login=?', [(int) $user['id'],(string) $user['login']]);
	if(!$data){
		$app['db']->update('utilisateur', 
				array(
					'login' => $user['login'],
					'mot_passe'=> $user['mot_passe'],
					'nom'=> $user['nom'],
					'prenom'=> $user['prenom'],
					'email'=> $user['email'],
					'role'=> $user['role'],
					'id_entite'=> $user['entite']['id']
				),
				array('id' => $user['id'])
				);
	    $reponse = array(
				'operation' =>'ok',
				'user'=>$user,
				'message'=> 'Modification réussi'
				);
		return  $app->json($reponse);   
	}
   
    $reponse = array(
				'operation' => 'ko' , 
				'erreur' => 'UtilisateurExiste',
				'message' => "Utilisateur " .$user['login']. " existant!!");
    return $app->json($reponse);
});

/**
 * @api {delete} /deleteUser/:id Supprimer un utilisateur
 * @apiName deleteUser
 * @apiGroup Utilisateurs
 * 
 * @apiParam {number} id Identifiant de l'utilsateur.
 * 
 * @apiSuccess {String} Objet JSON avec "operation" : "Suppression exécuté"
 * @apiSuccessExample Success-Response:
 *     {
 *       "operation": "Suppression exécuté."
 *     }
 * 
 */

$app->post('/deleteUser/', function(Request $request) use ($app){
    $user=getDataFromRequest($request);
    $app['db']->delete("utilisateur", array("id" => $user['id']));
    $reponse = array(
        'operation' =>'ok',
        'user'=>$user,
        'message'=> 'Suppression réussi'
        );
   
    return  $app->json($reponse);
});

/**
 * @api {get} /listEntites/ Affichage de la liste des entités
 * @apiName listEntites
 * @apiGroup Entite
 * 
 * @apiSuccess {String[]} des Objets Entites dans un Objet JSON
 * @apiSuccessExample Success-Response:
 *     {
 *       {nom: "nom", type: "type"};
 *     }
 * @apiError ListeEntitésVide 'La liste des entités est vide' si aucune entité n a été insérer.
 * @apiErrorExample Error-Response:
 *     {
 *			"operation": "ko",
 *			"erreur": "ListeUtilisateursVide",
 *			"message": "La liste des utilisateurs est vide. Aucun utilisateur n'est ajouté !"
 *     }
 */

$app->get('/listEntites/', function() use ($app){
    $sql = "SELECT id,nom,type FROM entite";
    $entites = $app['db']->fetchAll($sql,array());
    if(is_null($entites)){
        $response = array('operation' => 'ko' , 'erreur' => "La liste des entities est vide. Aucun entite n'est ajouté !!");
        return $app->json($reponse);
    }
    $response = [];
    foreach ($entites as $u) {
        $response[] = [
            'id' => $u['id'],
            'nom' => $u['nom'],
            'type' => $u['type'],
        ];
    }
    return $app->json($response);
});

/**
 * @api {post} /addEntite/:nom/:type/:id_parent Ajouter une entité
 * @apiName addEntite
 * @apiGroup Entite
 * 
 * @apiSuccess {String} Objet JSON avec "operation" : "Ajout réussi"
 * @apiSuccessExample Success-Response:
 *     {
 *       "operation": "Ajout réussi"
 *     }
 * @apiError EntiteExiste 'L Entite .$nom existe déjà' si les données saisies concerne une entité existante.
 * @apiErrorExample Error-Response:
 *     {
 *			"operation": "ko",
 *			"erreur": "EntiteExiste",
 *			"message": "L Entite .$nom existe déjà"
 *     }
 */

$app->post('/addEntite/', function(Request $request) use ($app){
	$entite=getDataFromRequest($request); 
    if($entite['type'] === 'Direction'){
        $entite['id_parent'] = 1;
    }elseif ($entite['type'] === 'Division') {
        $entite['id_parent'] = 2;
    }elseif ($entite['type'] === 'Service') {
        $entite['id_parent'] = 3;
    }else{
        $entite['id_parent'] = 0;
    }
	$app['db']->insert('entite', 
							array(
								'nom' => $entite['nom'],
								'type'=> $entite['type'],
								'id_parent'=> $entite['id_parent']
								)
	);
	$entite['id']=$app['db']->lastInsertId();
	$reponse = array(
                    'operation' =>'ok',
                    'entite'=>$entite,
                    'message'=> 'Ajout réussi'
                    );
   
	return  $app->json($reponse);    
});


/**
 * @api {put} /updateEntite/:id/:nom/:type/:id_parent Modifier une entité
 * @apiName updateEntite
 * @apiGroup Entite
 * 
 * @apiParam {number} id Identifiant de l'entité.
 * @apiParam {String} nom Nom de l'entité.
 * @apiParam {String} type Type de l'entité.
 * @apiParam {String} id_parent Identifient du parent de l'entité.
 * 
 * @apiSuccess {String} Objet JSON avec "operation" : "Modification réussite"
 * @apiSuccessExample Success-Response:
 *     {
 *       "operation": "Modification réussite."
 *     }
 * 
 */
$app->post('/updateUser/', function(Request $request) use ($app){
  $user=getDataFromRequest($request);
  $data=$app['db']->fetchAssoc('SELECT * FROM utilisateur WHERE id<>? AND login=?', [(int) $user['id'],(string) $user['login']]);
    if(!$data){
        $app['db']->update('utilisateur', 
                array(
                    'login' => $user['login'],
                    'mot_passe'=> $user['mot_passe'],
                    'nom'=> $user['nom'],
                    'prenom'=> $user['prenom'],
                    'email'=> $user['email'],
                    'role'=> $user['role'],
                    'id_entite'=> $user['entite']['id']
                ),
                array('id' => $user['id'])
                );
        $reponse = array(
                'operation' =>'ok',
                'user'=>$user,
                'message'=> 'Modification réussi'
                );
        return  $app->json($reponse);   
    }
   
    $reponse = array(
                'operation' => 'ko' , 
                'erreur' => 'UtilisateurExiste',
                'message' => "Utilisateur " .$user['login']. " existant!!");
    return $app->json($reponse);
});
//////
$app->post('/updateEntite/', function(Request $request) use ($app){
	$entite=getDataFromRequest($request);
    $app['db']->update('entite', 
        array(
            'nom' => $entite['nom'],
            'type'=> $entite['type'],
            
        ),
        array('id' => $entite['id'])
        );
		
$reponse = array(
                'operation' =>'ok',
                'entite'=>$entite,
                'message'=> 'Modification réussi'
        );
return  $app->json($reponse);
});

/**
 * @api {delete} /deleteEntite/:id Supprimer une entité
 * @apiName deleteEntite
 * @apiGroup Entite
 * 
 * @apiParam {number} id Identifiant de l'entité.
 * 
 * @apiSuccess {String} Objet JSON avec "operation" : "Suppression exécuté"
 * @apiSuccessExample Success-Response:
 *     {
 *       "operation": "Suppression exécuté."
 *     }
 * 
 */

$app->post('/deleteEntite/', function(Request $request) use ($app){	
	$entite=getDataFromRequest($request);
    $app['db']->delete("entite", array("id" => $entite['id']));
    $reponse = array(
        'operation' =>'ok',
        'entite'=>$entite,
        'message'=> 'Suppression réussi'
        );
    return $app->json($reponse);
});

$app->get('/listInstruction/', function() use ($app){
    $sql = "SELECT id,libelle FROM instruction";
    $entites = $app['db']->fetchAll($sql,array());
    if(is_null($entites)){
        $response = array('operation' => 'ko' , 'erreur' => "La liste des instructions est vide. Aucun instruction n'est ajouté !!");
        return $app->json($reponse);
    }
    $response = [];
    foreach ($entites as $u) {
        $response[] = [
            'id' => $u['id'],
            'libelle' => $u['libelle']
        ];
    }
    return $app->json($response);
});

$app->run();

