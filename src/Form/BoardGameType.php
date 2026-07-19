<?php

namespace App\Form;

use App\Entity\BoardGame;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Formulaire du catalogue de la ludothèque (champs catalogue uniquement).
 *
 * Le statut, l'emprunteur et les dates d'emprunt ne sont pas exposés ici :
 * ils sont gérés exclusivement par le workflow d'approbation admin
 * (LudothequeController::approve/reject/return).
 */
class BoardGameType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $labelAttr = ['class' => 'block text-xs font-bold uppercase tracking-wider text-text-primary mb-1'];
        $inputAttr = ['class' => 'w-full rounded-lg border border-custom bg-custom-secondary px-4 py-3 text-text-primary focus:border-custom-orange focus:outline-none focus:ring-1 focus:ring-custom-orange'];

        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'label_attr' => $labelAttr,
                'attr' => array_merge($inputAttr, ['placeholder' => 'Ex : Catan']),
                'constraints' => [new NotBlank(message: 'Le titre est obligatoire.')],
            ])
            ->add('category', TextType::class, [
                'label' => 'Catégorie',
                'label_attr' => $labelAttr,
                'required' => false,
                'attr' => array_merge($inputAttr, ['placeholder' => 'Ex : Stratégie']),
            ])
            ->add('maxPlayers', IntegerType::class, [
                'label' => 'Nombre maximum de joueurs',
                'label_attr' => $labelAttr,
                'required' => false,
                'attr' => array_merge($inputAttr, ['placeholder' => 'Ex : 4', 'min' => 1]),
            ])
            ->add('durationMinutes', IntegerType::class, [
                'label' => 'Durée (minutes)',
                'label_attr' => $labelAttr,
                'required' => false,
                'attr' => array_merge($inputAttr, ['placeholder' => 'Ex : 90', 'min' => 1]),
            ])
            ->add('condition', ChoiceType::class, [
                'label' => 'État',
                'label_attr' => $labelAttr,
                'required' => false,
                'choices' => [
                    '-- Choisir --' => '',
                    'Neuf' => 'Neuf',
                    'Bon état' => 'Bon état',
                    'Usé' => 'Usé',
                    'Abîmé' => 'Abîmé',
                ],
                'attr' => $inputAttr,
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'label_attr' => $labelAttr,
                'required' => false,
                'attr' => array_merge($inputAttr, ['placeholder' => 'Notes complémentaires...', 'rows' => 4]),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BoardGame::class,
        ]);
    }
}
