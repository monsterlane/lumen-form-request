<?php

namespace Monsterlane\Http;

use Laravel\Lumen\Http\Request;
use Laravel\Lumen\Http\Redirector;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidatesWhenResolvedTrait;
use Illuminate\Contracts\Validation\ValidatesWhenResolved;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;

class FormRequest extends Request implements ValidatesWhenResolved
{
	use ValidatesWhenResolvedTrait;

	/**
	 * The container instance.
	 *
	 * @var Container
	 */
	protected $container;

	/**
	 * The redirector instance.
	 *
	 * @var Redirector
	 */
	protected $redirector;

	/**
	 * The URI to redirect to if validation fails.
	 *
	 * @var string
	 */
	protected $redirect;

	/**
	 * The route to redirect to if validation fails.
	 *
	 * @var string
	 */
	protected $redirectRoute;

	/**
	 * The controller action to redirect to if validation fails.
	 *
	 * @var string
	 */
	protected $redirectAction;

	/**
	 * The key to be used for the view error bag.
	 *
	 * @var string
	 */
	protected $errorBag = 'default';

	/**
	 * The validator instance.
	 *
	 * @var Validator
	 */
	protected $validator;

	/**
	 * Get the validator instance for the request.
	 *
	 * @return Validator
	 */
	protected function getValidatorInstance()
	{
		if ($this->validator) {
			return $this->validator;
		}

		$factory = $this->container->make(ValidationFactory::class);

		if (method_exists($this, 'validator')) {
			$validator = $this->container->call([$this, 'validator'], compact('factory'));
		} else {
			$validator = $this->createDefaultValidator($factory);
		}

		if (method_exists($this, 'withValidator')) {
			$this->withValidator($validator);
		}

		$this->setValidator($validator);

		return $this->validator;
	}

	/**
	 * Create the default validator instance.
	 *
	 * @param ValidationFactory $factory
	 * @return Validator
	 */
	protected function createDefaultValidator(ValidationFactory $factory)
	{
		return $factory->make(
			$this->validationData(), $this->container->call([$this, 'rules']),
			$this->messages(), $this->attributes()
		);
	}

	/**
	 * Get data to be validated from the request.
	 *
	 * @return array
	 */
	protected function validationData()
	{
		return $this->all();
	}

	/**
	 * Handle a failed validation attempt.
	 *
	 * @param Validator $validator
	 * @return void
	 *
	 * @throws ValidationException
	 */
	protected function failedValidation(Validator $validator)
	{
		$errors = $validator->getMessageBag()->toArray();
		$response = new JsonResponse($errors, 422);

		throw new HttpResponseException($response);
	}

	/**
	 * Determine if the request passes the authorization check.
	 *
	 * @return bool
	 */
	protected function passesAuthorization()
	{
		if (method_exists($this, 'authorize')) {
			return $this->container->call([$this, 'authorize']);
		}

		return true;
	}

	/**
	 * Handle a failed authorization attempt.
	 *
	 * @return void
	 *
	 * @throws AuthorizationException
	 */
	protected function failedAuthorization()
	{
		throw new AuthorizationException('This action is unauthorized.');
	}

	/**
	 * Get the validated data from the request.
	 *
	 * @return array
	 */
	public function validated()
	{
		return $this->validator->validated();
	}

	/**
	 * Get custom messages for validator errors.
	 *
	 * @return array
	 */
	public function messages()
	{
		return [];
	}

	/**
	 * Get custom attributes for validator errors.
	 *
	 * @return array
	 */
	public function attributes()
	{
		return [];
	}

	/**
	 * Set the Validator instance.
	 *
	 * @param Validator $validator
	 * @return $this
	 */
	public function setValidator(Validator $validator)
	{
		$this->validator = $validator;

		return $this;
	}

	/**
	 * Set the Redirector instance.
	 *
	 * @param  Redirector  $redirector
	 * @return $this
	 */
	public function setRedirector(Redirector $redirector)
	{
		$this->redirector = $redirector;

		return $this;
	}

	/**
	 * Set the container implementation.
	 *
	 * @param Container $container
	 * @return $this
	 */
	public function setContainer(Container $container)
	{
		$this->container = $container;

		return $this;
	}


	/**
	 * Validate the incoming request by checking the validations first then checking
	 * the authorization.
	 *
	 * @return void
	 * @throws AuthorizationException
	 * @throws ValidationException
	 */
	public function validateResolved()
	{

		$validator = $this->getValidatorInstance();

		if ($validator->fails()) {
			$this->failedValidation($validator);
		}

		if (!$this->passesAuthorization()) {
			$this->failedAuthorization();
		}
	}
}
