<?php

namespace Inneair\SynappsBundle\Controller;

use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\View\View;
use Inneair\Synapps\Exception\UniqueConstraintException;
use Inneair\SynappsBundle\Exception\ValidationException;
use Inneair\SynappsBundle\Http\ErrorsContent;
use Inneair\SynappsBundle\Http\ErrorResponseContent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Abstract web controller providing additional services over the FOS REST controller, and the Symfony controller.
 */
abstract class AbstractController extends FOSRestController
{
    /**
     * Domain name for translations.
     * @var string
     */
    const CONTROLLER_DOMAIN = 'controllers';

    /**
     * Translator service.
     * @var TranslatorInterface
     */
    protected $translator;
    /**
     * Logging service.
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Sets the translator service.
     *
     * NOTE: this method is automatically invoked by the framework, and should never be called manually.
     *
     * @param TranslatorInterface $translator Translator service.
     */
    public function setTranslator(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Sets the logging service.
     *
     * NOTE: this method is automatically invoked by the framework, and should never be called manually.
     *
     * @param LoggerInterface $logger Logging service.
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Initializes the controller.
     *
     * NOTE: this method is automatically invoked by the framework, and should never be called manually.
     * Actually, this method does nothing but logging the controller is ready. It may be overridden by concrete
     * controllers, to perform additional initializations other than raw instanciations (but calling this parent method
     * is always mandatory to ensure forward compatibility).
     */
    public function init()
    {
        $this->logger->debug('Controller \'' . static::class . '\' is up.');
    }

    /**
     * Creates and returns a FormInterface instance from the given type.
     *
     * @param string|FormInterface $type The built type of the form (defaults to 'form').
     * @param mixed $data The initial data for the form (defaults to <code>null</code>).
     * @param array $options Options for the form (defaults to an empty array).
     * @param string $name Form name (defaults to <code>null</code>).
     * @return FormInterface A form instance.
     * @throws InvalidOptionsException If any given option is not applicable to the given type.
     */
    public function createNamedForm($type = 'form', $data = null, array $options = array(), $name = null)
    {
        return $this->container->get('form.factory')->createNamed($name, $type, $data, $options);
    }

    /**
     * Creates and returns a FormBuilderInterface instance from the given type.
     *
     * @param string|FormInterface $type The built type of the form (defaults to 'form').
     * @param mixed $data The initial data for the form (defaults to <code>null</code>).
     * @param array $options Options for the form (defaults to an empty array).
     * @param string $name Form name (defaults to <code>null</code>).
     * @return FormBuilderInterface A form builder instance.
     * @throws InvalidOptionsException If any given option is not applicable to the given type
     */
    public function createNamedBuilder($type = 'form', $data = null, array $options = array(), $name = null)
    {
        return $this->container->get('form.factory')->createNamedBuilder($name, $type, $data, $options);
    }

    /**
     * Sends a 409 HTTP status code due to a conflict.
     *
     * @param mixed $data The data to be enclosed in the response (defaults to <code>null</code>).
     * @return View The view mapped to a 409 HTTP status code.
     */
    protected function createHttpConflictView($data = null)
    {
        return $this->view($data, Response::HTTP_CONFLICT);
    }

    /**
     * Sends a 404 HTTP status code for a data not found exception.
     *
     * @param mixed $data The data to be enclosed in the response (defaults to <code>null</code>).
     * @return View The view mapped to a 404 HTTP status code.
     */
    protected function createHttpNotFoundView($data = null)
    {
        return $this->view($data, Response::HTTP_NOT_FOUND);
    }

    /**
     * Sends a 400 HTTP status code for a data not found exception.
     *
     * @param mixed $data The data to be enclosed in the response (defaults to <code>null</code>).
     * @return View The view mapped to a 400 HTTP status code.
     */
    protected function createHttpBadRequestView($data = null)
    {
        return $this->view($data, Response::HTTP_BAD_REQUEST);
    }

    /**
     * Converts unique constraint exception on a property into a 409 HTTP status code.
     *
     * @param string $property Name of the property where a duplicated value was discovered.
     * @return View The view mapped to a 409 HTTP status code.
     */
    protected function uniqueViolationToHttpConflictView(UniqueConstraintException $exception)
    {
        $errors = new ErrorsContent();
        $errors->fields[$exception->getProperty()][] = $this->translator->trans(
            'controller.general.uniquevalueerror',
            array(),
            self::CONTROLLER_DOMAIN
        );
        $content = new ErrorResponseContent($errors);
        return $this->view($content, Response::HTTP_CONFLICT);
    }

    /**
     * Converts validation errors into a 400 HTTP status code.
     *
     * @param ValidationException $exception Validation exception.
     * @return View The view mapped to a 400 HTTP status code.
     */
    protected function validationExceptionToHttpBadRequestView(ValidationException $exception)
    {
        $errors = new ErrorsContent();
        $globalErrors = $exception->getGlobalErrors();
        foreach ($globalErrors as $error) {
            $errors->global[] = $error;
        }

        $allFieldErrors = $exception->getFieldErrors();
        foreach ($allFieldErrors as $fieldName => $fieldErrors) {
            $errors->mergeFieldErrors($fieldName, $fieldErrors);
        }

        $content = new ErrorResponseContent($errors);
        return $this->createHttpBadRequestView($content);
    }
}
