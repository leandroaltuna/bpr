<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Conversacion extends CI_Controller {

	function __construct()
	{
		parent::__construct();
		$this->load->library('form_validation');
		$this->load->helper('url');

		if (!$this->ion_auth->logged_in())
		{
			redirect('auth/login');
		}

		$this->load->model('conversacion_model');
	}

	function index($cod_categoria = null, $cod_consulta = null)
	{
		$cod_categoria = ($cod_categoria == null) ? 0 : $cod_categoria;
		$cod_consulta = ($cod_consulta == null) ? 0 : $cod_consulta;
		
		$this->parameters['title'] = 'Conversacion';
		$this->parameters['description'] = 'Historial de la consulta';
		$this->parameters['main_content'] = 'conversacion';
		$this->parameters['categoria'] = $cod_categoria;
		$this->parameters['consulta'] = $cod_consulta;
		$this->parameters['user'] = $this->ion_auth->user()->row();

		$condicional = array( 'group_id' => $cod_categoria, 'cod_consulta' => $cod_consulta );
		$this->parameters['cabecera'] = $this->conversacion_model->select_data('Consulta', $condicional)->row();

		$join = 'Consulta_Detalle.username = users.username';
		$sorted = 'nro_detalle asc';
		// $this->parameters['contenido'] = $this->conversacion_model->select_data_join( 'Consulta_Detalle', 'users', $join, $condicional, $sorted )->result();

		$this->load->view('frontend/template', $this->parameters);
	}

	function view_chat()
	{
		$cod_categoria = $this->input->post('group_id');
		$cod_consulta = $this->input->post('cod_consulta');

		$selected = "Consulta_Detalle.username, Consulta_Detalle.cod_consulta, Consulta_Detalle.nro_detalle, Consulta_Detalle.group_id,Consulta_Detalle.mensaje, Consulta_Detalle.tipo, ( RTRIM(CONVERT(char, Consulta_Detalle.fecha,103)) + ' ' + RTRIM(CONVERT(char, Consulta_Detalle.fecha,108))) as fecha, users.type, users.first_name, users.last_name";
		$condicional = array( 'group_id' => $cod_categoria, 'cod_consulta' => $cod_consulta );
		$join = 'Consulta_Detalle.username = users.username';
		$sorted = 'nro_detalle asc';
		$this->parameters['contenido'] = $this->conversacion_model->select_data_join( $selected, 'Consulta_Detalle', 'users', $join, $condicional, $sorted )->result();

		$data['datos'] = $this->parameters;
		$this->load->view('frontend/json/json_view', $data);
	}

	function mensajes()
	{
		$user = $this->ion_auth->user()->row();

		$cod_consulta = $this->input->post('cod_consulta');
		$cod_categoria = $this->input->post('group_id');

		$this->condicional = array( 'group_id' => $cod_categoria, 'cod_consulta' => $cod_consulta );

		$consulta = $this->conversacion_model->select_data( 'Consulta', $this->condicional )->row();
		$username_consulta = $consulta->username;

		$number = $this->conversacion_model->count_result( $this->condicional, 'Consulta_Detalle' );
		$nro_detalle = $number + 1;

		$this->table_consulta_detalle = $this->conversacion_model->get_fields('Consulta_Detalle');

		$this->array_fields = array( 'username', 'nro_detalle', 'tipo', 'username_consultor', 'fecha' );

		$this->data_detail['username'] = $username_consulta;
		$this->data_detail['nro_detalle'] = $nro_detalle;
		$this->data_detail['tipo'] = $user->type;
		$this->data_detail['username_consultor'] = ($user->type == 1) ? $user->username : null;
		// $this->data_detail['fecha'] = date('Y/m/d H:i:s');
		$this->data_detail['fecha'] = date('d/m/Y H:i:s');

		foreach ($this->table_consulta_detalle as $key => $name_field)
		{
			if ( !in_array( $name_field, $this->array_fields ) )
			{
				$this->data_detail[$name_field] = ($this->input->post($name_field) == '') ? null : $this->input->post($name_field);
			}
		}

		$this->result = $this->conversacion_model->insert_data( $this->data_detail, 'Consulta_Detalle' );


		if ( $this->result > 0 ) 
		{
			$this->message = "Se envio tu mensaje!";
			$this->estado = 1;
		}
		else
		{
			$this->message = "Se ha producido un error, recargue, verifique y vuelvalo a intentar.";
			$this->estado = 0;
		}

		$this->parameters['msg'] = $this->message;
		$this->parameters['estado'] = $this->estado;

		$data['datos'] = $this->parameters;
		$this->load->view('frontend/json/json_view', $data);
	}

	function new_message()
	{
		$find_issues = array();
		$group_in = array();

		$user = $this->ion_auth->user()->row();
		$users_groups = $this->ion_auth->get_users_groups($user->id)->result();

		foreach ($users_groups as $row)
		{
			array_push( $group_in, $row->id );
		}

		$selected = 'username, cod_consulta, group_id, MAX(nro_detalle) as nro_detalle';
		$field_conditional = 'group_id';
		$group = array('group_id', 'cod_consulta', 'username');
		$this->first_messages = $this->conversacion_model->select_first_message( $selected, 'Consulta_Detalle', $field_conditional, $group_in, $group )->result();

		foreach ($this->first_messages as $row)
		{
			if ( $row->nro_detalle == 1 )
			{
				array_push( $find_issues, array( 'username' => $row->username, 'cod_consulta' => $row->cod_consulta, 'group_id' => $row->group_id ) );
			}
		}

		$content = array();
		$number_alert = 0;
		if ( count($find_issues) > 0 )
		{
			for ($i=0; $i < count($find_issues); $i++)
			{
				$condicional = array( 'username' => $find_issues[$i]['username'], 'cod_consulta' => $find_issues[$i]['cod_consulta'], 'group_id' => $find_issues[$i]['group_id'] );
				$this->data = $this->conversacion_model->select_data( 'Consulta', $condicional )->row();

				array_push($content, $this->data );
				$number_alert++;
			}
		}

		$this->parameters['contenido'] = $content;
		$this->parameters['alert'] = $number_alert;
		$data['datos'] = $this->parameters;
		$this->load->view('frontend/json/json_view', $data);

	}

}

?>