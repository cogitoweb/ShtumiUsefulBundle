<?php

namespace Shtumi\UsefulBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use Symfony\Component\HttpFoundation\Response;

class AjaxAutocompleteJSONController extends Controller
{

    public function getJSONAction()
    {

        $em = $this->get('doctrine')->getManager();
        $request = $this->getRequest();

        $entities = $this->get('service_container')->getParameter('shtumi.autocomplete_entities');

        $entity_alias = $request->get('entity_alias');
        $entity_inf = $entities[$entity_alias];

        if ($entity_inf['role'] !== 'IS_AUTHENTICATED_ANONYMOUSLY'){
            if (false === $this->get('security.context')->isGranted( $entity_inf['role'] )) {
                throw new AccessDeniedException();
            }
        }

        $letters = $request->get('letters');
        $maxRows = $request->get('maxRows');

        switch ($entity_inf['search']){
            case "begins_with":
                $like = $letters . '%';
            break;
            case "ends_with":
                $like = '%' . $letters;
            break;
            case "contains":
                $like = '%' . $letters . '%';
            break;
            default:
                throw new \Exception('Unexpected value of parameter "search"');
        }

        $property = $entity_inf['property'];

        //inizio 2z ->
        if ($entity_inf['case_insensitive']) {
                $where_clause_lhs = ' LOWER(e.' . $property . ')';
                $where_clause_rhs = 'LIKE LOWER(:like)';
        } else {

                $where_clause_lhs = ' e.' . $property;
                $where_clause_rhs = 'LIKE :like';
        }
        
        $fixed_where = isset($entity_inf["fixed_where"]) ? str_replace('$class$', 'e', $entity_inf["fixed_where"]).' AND ' : '';
        $join = isset($entity_inf["join"]) ? str_replace('$class$', 'e', $entity_inf["join"]) : '';
        
        $sql = 'SELECT distinct e.' . $property . ' %addselect%
             FROM ' . $entity_inf['class'] . ' e '. $join . ' WHERE '.$fixed_where.' (' .
             $where_clause_lhs . ' ' . $where_clause_rhs . ' ' . ' %addwhere%) ORDER BY ';
        
        $order_by = ' e.' . $property;
        
        $add_where = "";
        $add_select = "";
        foreach ($entity_inf["sub_properties"] as $sub) {

            $add_select .= str_replace('$property$', $sub["property"], $sub["select_query_part"]);
            $add_where .= str_replace('$property$', $sub["property"], $sub["where_query_part"]);
            $order_by = str_replace('$default_sort$', $order_by, $sub["order_query_part"]);
        }
        
        $sql = str_replace("%addselect%", $add_select, $sql);
        $sql = str_replace("%addwhere%", $add_where, $sql);
        $sql .= $order_by;

        $query = $em->createQuery($sql)
            ->setParameter('like', str_replace('\\', '\\\\',$like));
        
        // inject eventuale search plain
        if(strpos($sql, ':search') !== false)
        {
            $query->setParameter('search', str_replace('\\', '\\\\',$letters));
        }
        // inject eventuale locale
        if(strpos($sql, ':locale') !== false)
        {
            $query->setParameter('locale', $this->get('request')->get('_locale'));
        }
        
        $results = $query
            ->setMaxResults($maxRows)
            ->getScalarResult();
        // fine 2z ->
        
        $res = array();
        foreach ($results AS $r){
            $res[] = $r[$entity_inf['property']];
        }

        return new Response(json_encode($res));

    }
}
