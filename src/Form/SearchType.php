<?php


namespace App\Form;


use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class SearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            // You can add as much as you want, see API docs which countries are supported
            ->add('country', ChoiceType::class, [
                'choices' => [
                    'Austria' => 'aut',
                    'Belgium' => 'bel',
                    'China' => 'chn',
                    'France' => 'fra',
                    'Italy' => 'ita',
                    'Japan' => 'jpn',
                    'Lithuania' => 'ltu',
                    'Mexico' => 'mex',
                    'Poland' => 'pol',
                    'Sweden' => 'swe'
                ],
                'label' => 'Please select the Country'
            ])
            // You can also add how many years you want
            ->add('year', ChoiceType::class, [
                'choices' => [
                    '2020' => 2020,
                    '2021' => 2021,
                    '2022' => 2022,
                    '2023' => 2023,
                    '2024' => 2024,
                    '2025' => 2025,
                    '2026' => 2026,
                    '2027' => 2027,
                    '2028' => 2028,
                    '2029' => 2029,
                    '2030' => 2030,
                ],
                'label' => 'Choose the year you want to search'
            ])
            ->add('search', SubmitType::class, ['attr' => ['class' => 'my-btn w-100']]);
    }
}