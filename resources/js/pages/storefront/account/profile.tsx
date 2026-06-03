import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AccountNav } from './account-nav';

type Profile = { name: string; email: string; phone: string | null };

export default function CustomerProfile({ profile }: { profile: Profile }) {
    return (
        <div className="mx-auto max-w-2xl">
            <Head title="Mi perfil" />
            <h1 className="mb-6 text-2xl font-semibold">Mi cuenta</h1>
            <AccountNav />

            <section className="mb-10">
                <h2 className="mb-4 text-lg font-medium">Datos personales</h2>
                <Form action="/cuenta/perfil" method="put" className="space-y-4">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nombre</Label>
                                <Input id="name" name="name" defaultValue={profile.name} required />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="email">Correo</Label>
                                <Input id="email" name="email" type="email" defaultValue={profile.email} required />
                                <InputError message={errors.email} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="phone">Teléfono</Label>
                                <Input id="phone" name="phone" defaultValue={profile.phone ?? ''} />
                                <InputError message={errors.phone} />
                            </div>
                            <Button disabled={processing}>Guardar</Button>
                        </>
                    )}
                </Form>
            </section>

            <section>
                <h2 className="mb-4 text-lg font-medium">Cambiar contraseña</h2>
                <Form action="/cuenta/password" method="put" className="space-y-4" options={{ preserveScroll: true }} resetOnSuccess>
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="current_password">Contraseña actual</Label>
                                <Input id="current_password" name="current_password" type="password" required />
                                <InputError message={errors.current_password} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="password">Nueva contraseña</Label>
                                <Input id="password" name="password" type="password" required />
                                <InputError message={errors.password} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">Confirmar contraseña</Label>
                                <Input id="password_confirmation" name="password_confirmation" type="password" required />
                            </div>
                            <Button disabled={processing}>Actualizar contraseña</Button>
                        </>
                    )}
                </Form>
            </section>
        </div>
    );
}
