<?php

namespace App\Form;

use App\Entity\CarouselSlide;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class CarouselSlideType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $labelAttr = ['class' => 'block text-xs font-bold uppercase tracking-wider text-text-primary mb-1'];
        $inputAttr = ['class' => 'w-full rounded-lg border border-custom bg-custom-secondary px-4 py-3 text-text-primary focus:border-custom-orange focus:outline-none focus:ring-1 focus:ring-custom-orange'];

        $builder
            ->add('position', IntegerType::class, [
                'label' => 'Ordre d\'affichage',
                'label_attr' => $labelAttr,
                'attr' => array_merge($inputAttr, ['min' => 0]),
                'required' => true,
                'data' => $builder->getData()?->getPosition() ?? 0,
            ])
            ->add('tag', TextType::class, [
                'label' => 'Étiquette (tag)',
                'label_attr' => $labelAttr,
                'attr' => array_merge($inputAttr, ['placeholder' => 'Ex : Prochain événement']),
                'constraints' => [new NotBlank(message: 'L\'étiquette est obligatoire.')],
            ])
            ->add('tagColor', ChoiceType::class, [
                'label' => 'Couleur de l\'étiquette',
                'label_attr' => $labelAttr,
                'choices' => [
                    'Orange' => 'text-custom-orange',
                    'Cyan' => 'text-cyan-400',
                    'Violet' => 'text-purple-400',
                    'Rose' => 'text-rose-400',
                    'Émeraude' => 'text-emerald-400',
                    'Ambre' => 'text-amber-400',
                ],
                'attr' => $inputAttr,
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'label_attr' => $labelAttr,
                'attr' => array_merge($inputAttr, ['placeholder' => 'Ex : Senses Etch - Champs de Valoris']),
                'constraints' => [new NotBlank(message: 'Le titre est obligatoire.')],
            ])
            ->add('date', TextType::class, [
                'label' => 'Date / sous-titre',
                'label_attr' => $labelAttr,
                'attr' => array_merge($inputAttr, ['placeholder' => 'Ex : 25 JAN ou PUBLIÉ LUNDI']),
                'constraints' => [new NotBlank(message: 'La date est obligatoire.')],
            ])
            ->add('btnText', TextType::class, [
                'label' => 'Texte du bouton',
                'label_attr' => $labelAttr,
                'attr' => array_merge($inputAttr, ['placeholder' => 'Ex : S\'INSCRIRE']),
                'constraints' => [new NotBlank(message: 'Le texte du bouton est obligatoire.')],
            ])
            ->add('btnClass', ChoiceType::class, [
                'label' => 'Style du bouton',
                'label_attr' => $labelAttr,
                'choices' => [
                    'Orange' => 'bg-custom-orange group-hover:bg-orange-600 shadow-custom-orange/20',
                    'Cyan' => 'bg-cyan-600 group-hover:bg-cyan-700 shadow-cyan-500/20',
                    'Violet' => 'bg-purple-600 group-hover:bg-purple-700 shadow-purple-500/20',
                    'Rose' => 'bg-rose-600 group-hover:bg-rose-700 shadow-rose-500/20',
                    'Émeraude' => 'bg-emerald-600 group-hover:bg-emerald-700 shadow-emerald-500/20',
                    'Ambre' => 'bg-amber-600 group-hover:bg-amber-700 shadow-amber-500/20',
                ],
                'attr' => $inputAttr,
            ])
            ->add('btnUrl', TextType::class, [
                'label' => 'Lien du bouton (URL)',
                'label_attr' => $labelAttr,
                'required' => false,
                'attr' => array_merge($inputAttr, ['placeholder' => 'Ex : /activite/1/inscrire ou https://...']),
                'help' => 'Uniquement un chemin relatif (/…) ou une URL http(s).',
                'help_attr' => ['class' => 'text-[10px] text-text-secondary mt-1 block'],
                'constraints' => [
                    new Callback(function (?string $value, ExecutionContextInterface $context): void {
                        if ($value === null || $value === '') {
                            return;
                        }
                        $value = trim($value);
                        if (str_starts_with($value, '/') && !str_starts_with($value, '//')) {
                            return;
                        }
                        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
                        if (!\in_array($scheme, ['http', 'https'], true)) {
                            $context->buildViolation('L\'URL doit être un chemin relatif (/…) ou commencer par http:// ou https://.')
                                ->addViolation();
                        }
                    }),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CarouselSlide::class,
        ]);
    }
}
