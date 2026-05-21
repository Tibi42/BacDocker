<?php

namespace App\Form;

use App\Entity\Article;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class ArticleType extends AbstractType
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
                'label' => 'Étiquette (tag / date)',
                'label_attr' => $labelAttr,
                'attr' => array_merge($inputAttr, ['placeholder' => 'Ex : 0/3 ou NEW']),
                'constraints' => [new NotBlank(message: 'L\'étiquette est obligatoire.')],
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre de l\'article',
                'label_attr' => $labelAttr,
                'attr' => array_merge($inputAttr, ['placeholder' => 'Ex : COMPTE-RENDU : L\'ASSAUT DES DRAGONS']),
                'constraints' => [new NotBlank(message: 'Le titre est obligatoire.')],
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Uploader une image',
                'label_attr' => $labelAttr,
                'required' => $builder->getData()?->getImage() === null, // Obligatoire seulement si pas d'image existante
                'mapped' => false,
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        mimeTypesMessage: 'Veuillez uploader une image valide (JPEG, PNG ou WebP).',
                    )
                ],
                'attr' => $inputAttr,
            ])
            ->add('galleryFiles', FileType::class, [
                'label' => 'Galerie d\'images (optionnel - plusieurs possibles)',
                'label_attr' => $labelAttr,
                'multiple' => true,
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new All([
                        new File(
                            maxSize: '5M',
                            mimeTypes: [
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ],
                            mimeTypesMessage: 'Veuillez uploader des images valides (JPEG, PNG ou WebP).',
                        )
                    ])
                ],
                'attr' => array_merge($inputAttr, ['multiple' => 'multiple']),
            ])

            ->add('url', TextType::class, [
                'label' => 'Lien externe ou route (optionnel)',
                'label_attr' => $labelAttr,
                'required' => false,
                'attr' => array_merge($inputAttr, ['placeholder' => 'Ex : /nouvelles/compte-rendu-assaut-des-dragons ou un lien externe']),
                'help' => 'Si renseigné, l\'article redirigera directement vers ce lien au clic.',
                'help_attr' => ['class' => 'text-[10px] text-text-secondary mt-1 block'],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Texte de l\'article',
                'label_attr' => $labelAttr,
                'required' => false,
                'attr' => [
                    'class' => 'hidden-content-editor hidden',
                    'style' => 'display:none',
                ],
                'help' => 'Ce texte sera mis en forme avec l\'éditeur de texte ci-dessous. Il sera affiché sur la page de détail si aucun lien externe n\'est défini.',
                'help_attr' => ['class' => 'text-[10px] text-text-secondary mt-1 block'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Article::class,
        ]);
    }
}
